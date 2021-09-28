<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Lazada2APIController;

class Lazada2ItemPriceUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:item-price-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the Lazada products based on the price in the Item Master';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try
        {
            $odataClient = new SapService();
            
            $count = 0;

            $moreItems = true;

            $items = [];

            while($moreItems){

                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ2_INTEGRATION','Yes')->skip($count)->get();//Live - Y/N

                if($getItems->isNotEmpty()){
                    
                    foreach($getItems as $item){

                        $items[] = [
                            'oldSku' => $item['U_OLD_SKU'],//Live - U_MPS_OLDSKU
                            'newSku' => $item['ItemCode'],
                            'price' => $item['ItemPrices']['8']['Price'], //live - $item['ItemPrices']['3']['Price']
                        ];

                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }

            }

            $lazadaAPI = new Lazada2APIController();
            
            $batch = array_chunk($items,50);
            
            $skuPayload = [];
            
            $skuPayloadCount = 0;

            foreach($batch as $b){

                foreach($b as $key){
                        
                    $newSku = $key['newSku'];
                    $oldSku = $key['oldSku'];
                    $price = $key['price'];
                    $getByNewSku = $lazadaAPI->getProductItem($newSku);

                    if(!empty($getByNewSku['data'])){
                        $lazadaItemId = $getByNewSku['data']['item_id'];
                        $finalSku = $newSku;
    
                    }else if($oldSku != null){
                        $getByOldSku = $lazadaAPI->getProductItem($oldSku);
    
                        if(!empty($getByOldSku['data'])){
                            $lazadaItemId = $getByOldSku['data']['item_id'];
                            $finalSku = $oldSku;
                        }
                    }

                    if(count($skuPayload) >= 20){
                        unset($skuPayload);
                    }

                    if(!empty($lazadaItemId) && !empty($finalSku)){
                        //Create SKU Payload
                        $skuPayload[] = "<Sku>
                                            <ItemId>".$lazadaItemId."</ItemId>
                                            <SellerSku>".$finalSku."</SellerSku>
                                            <Price>".$price."</Price>
                                        </Sku>";
                    }
                
                }

            }

            if(!empty($skuPayload)){
                $finalPayload = "<Request>
                                    <Product>
                                        <Skus>
                                            ".implode('',$skuPayload)."
                                        </Skus>
                                    </Product>
                                </Request>";
                //Run 
                $updatePrice = $lazadaAPI->updatePriceQuantity($finalPayload);

                if($updatePrice['code'] == 0){
                    $skuPayloadCount += count($skuPayload);
                }

            }
            
            if($skuPayloadCount > 0){
                Log::channel('lazada2.item_master')->info('Price updated on '.$skuPayloadCount.' Lazada SKU/s.');

            }else{
                Log::channel('lazada2.item_master')->warning('No Lazada items available.');

            }

        } catch (\Exception $e) {
            Log::channel('lazada2.item_master')->emergency($e->getMessage());

        }
    }
}
