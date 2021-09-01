<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Lazada2APIController;

class Lazada2ItemMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:item-master';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Item Master for 2nd Lazada Account';

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

            $skuPayloadCount = 0;

            $moreItems = true;

            while($moreItems){
            
                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ2_INTEGRATION','Yes')->skip($count)->get();//Live - Y/N
                
                if($getItems->isNotEmpty()){
                    
                    $lazadaAPI = new Lazada2APIController();
                    
                    foreach($getItems as $item){
                        //Price and Stocks
                        $sapPrice = $item['ItemPrices']['8']['Price']; //live - $item['ItemPrices']['3']['Price']
                        $sapStock = $item['QuantityOnStock'];
                        //Old and New SKU
                        $oldSku = $item['U_OLD_SKU']; //Live - U_MPS_OLDSKU
                        $newSku = $item['ItemCode']; //New SKU
                        $getByNewSku = $lazadaAPI->getProductItem($newSku);
                        
                        if(!empty($getByNewSku['data'])){
                            $lazadaItemId = $getByNewSku['data']['item_id'];
                            $finalSku = $newSku;

                            $odataClient->getOdataClient()->from('Items')
                                    ->whereKey($newSku)
                                    ->patch([
                                        'U_LAZ2_ITEM_CODE' => $lazadaItemId,
                                    ]);

                        }else if($oldSku != null){
                            $getByOldSku = $lazadaAPI->getProductItem($oldSku);
                            
                            if(!empty($getByOldSku['data'])){
                                $lazadaItemId = $getByOldSku['data']['item_id'];
                                $finalSku = $oldSku;
                                $oldSkuItemCode = $odataClient->getOdataClient()->from('Items')
                                                        ->where('U_OLD_SKU',$oldSku)
                                                        ->first();

                                $odataClient->getOdataClient()->from('Items')
                                        ->whereKey($oldSkuItemCode->ItemCode)
                                        ->patch([
                                            'U_LAZ2_ITEM_CODE' => $lazadaItemId,
                                        ]);
                            }
                        }
    
                        if(!empty($lazadaItemId) && !empty($finalSku)){
                             //Create SKU Payload
                            $skuPayload[] = "<Sku>
                                                <ItemId>".$lazadaItemId."</ItemId>
                                                <SellerSku>".$finalSku."</SellerSku>
                                                <Quantity>".$sapStock."</Quantity>
                                                <Price>".$sapPrice."</Price>
                                            </Sku>";
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

                        $updatePriceStock = $lazadaAPI->updatePriceQuantity($finalPayload);

                        if($updatePriceStock['code'] == 0){
                            $skuPayloadCount += count($skuPayload);
                        }
    
                    }

                    if(count($skuPayload) >= 20){
                        unset($skuPayload);
                    }
    
                    $count += count($getItems);
    
                }else{
                    $moreItems = false;
                }
    
            }

            if($skuPayloadCount > 0){
                Log::channel('lazada2.item_master')->info('stock and price updated on '.$skuPayloadCount.' Lazada SKU/s.');
            }

            if($count == 0){
                Log::channel('lazada2.item_master')->warning('No Lazada items available.');
            }


        } catch (\Exception $e) {
            Log::channel('lazada2.item_master')->emergency($e->getMessage());
        }
    }
}
