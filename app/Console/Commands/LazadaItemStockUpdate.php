<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LazadaAPIController;

class LazadaItemStockUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:item-stock-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the Lazada products based on the stock in the Item Master';

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
                
                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ_INTEGRATION','Yes')->skip($count)->get();//Live - Y/N

                if($getItems->isNotEmpty()){
                    
                    $lazadaAPI = new LazadaAPIController();

                    foreach($getItems as $item){

                        if($item['InventoryItem'] == 'tYES'){
                            //Stocks
                            $sapStock = $item['QuantityOnStock'];
                            //Old and New SKU
                            $oldSku = $item['U_OLD_SKU']; //Live - U_MPS_OLDSKU
                            $newSku = $item['ItemCode']; //New SKU
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
        
                            if(!empty($lazadaItemId) && !empty($finalSku)){
                                //Create SKU Payload
                                $skuPayload[] = "<Sku>
                                                    <ItemId>".$lazadaItemId."</ItemId>
                                                    <SellerSku>".$finalSku."</SellerSku>
                                                    <Quantity>".$sapStock."</Quantity>
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
                        $updateStock = $lazadaAPI->updatePriceQuantity($finalPayload);
                        
                        if($updateStock['code'] == 0){
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
                Log::channel('lazada.item_master')->info('Stock updated on '.$skuPayloadCount.' Lazada SKU/s.');

            }else{
                Log::channel('lazada.item_master')->warning('No Lazada items available.');

            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());

        }
    }
}
