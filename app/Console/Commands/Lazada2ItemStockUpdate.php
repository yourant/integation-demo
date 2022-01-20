<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Lazada2APIController;

class Lazada2ItemStockUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:item-stock-update';

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

            $moreItems = true;

            $items = [];

            while($moreItems){
                
                $getItems = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_LAZ2_INTEGRATION','Y')
                                                    ->where('U_LAZ2_ITEM_CODE','!=',null)
                                                    ->where('U_LAZ2_SELLER_SKU','!=',null)
                                                    ->where('U_UPDATE_INVENTORY','Y')
                                                    ->where('InventoryItem','tYES')
                                                    ->skip($count)
                                                    ->get();
                if($getItems->isNotEmpty()){

                    foreach($getItems as $item){

                        $items[] = [
                            'sellerSku' => $item['U_LAZ2_SELLER_SKU'],
                            'productId' => $item['U_LAZ2_ITEM_CODE'],
                            'stock' => $item['QuantityOnStock']
                        ];
                        
                    }

                    $count += count($getItems);

                }else{
                    $moreItems = false;
                }

            }

            $lazadaAPI = new Lazada2APIController();
            
            $batch = array_chunk($items,20);
            
            $errorList = [];

            $errorCount = 0;

            $successCount = 0;

            foreach($batch as $b){

                $skuPayload = [];

                $itemList = [];

                foreach($b as $key){

                    $sellerSku = $key['sellerSku'];
                    $productId = $key['productId'];
                    $stock = $key['stock'];
                    
                    //Create SKU Payload
                    $skuPayload[] = "<Sku>
                                        <ItemId>".$productId."</ItemId>
                                        <SellerSku>".$sellerSku."</SellerSku>
                                        <Quantity>".$stock."</Quantity>
                                    </Sku>";
                    
                    $itemList[] = [
                        'sellerSku' => $sellerSku,
                        'productId' => $productId
                    ];
                    
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
                        $successCount += count($skuPayload);
                    }else{
                        foreach($itemList as $item){
                            
                            $sellerSkuExist = array_search($item['sellerSku'], array_column($updateStock['detail'],'seller_sku'));
                            $productIdExist = array_search($item['productId'], array_column($updateStock['detail'],'seller_sku'));
    
                            if($sellerSkuExist !== false || $productIdExist !== false){
                                $errorCount++;
                            }else{
                                $successCount++;
                            }
    
                        }
    
                        foreach($updateStock['detail'] as $detail){
                            $errorList[] = "Seller SKU / Product ID: ".$detail['seller_sku']." - ".$detail['message'];
                        }
    
                    }
        
                }

            }

            if($successCount > 0){
                
                Log::channel('lazada2.item_master')->info('Update Stock - Stock updated on '.$successCount.' Lazada SKU/s.');
            
            }
            if($errorCount > 0){
               
                Log::channel('lazada2.item_master')->error("Update Stock - ".$errorCount." SKUs have issues while updating the stock: "."\n".implode("\n",$errorList));
            
            }
            if($successCount == 0 && $errorCount == 0){
                
                Log::channel('lazada2.item_master')->warning('Update Stock - No Lazada items available to be updated.');
            
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.item_master')->emergency($e->getMessage());

        }
    }
}
