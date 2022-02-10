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

            $moreItems = true;

            $items = [];

            while($moreItems){
                
                $getItems = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_LAZ_INTEGRATION','Y')
                                                    ->where('U_LAZ_ITEM_CODE','!=',null)
                                                    ->where('U_LAZ_SELLER_SKU','!=',null)
                                                    ->where('U_UPDATE_INVENTORY','Y')
                                                    ->where('InventoryItem','tYES')
                                                    ->skip($count)
                                                    ->get();

                if($getItems->isNotEmpty()){

                    foreach($getItems as $item){

                        $items[] = [
                            'sellerSku' => $item['U_LAZ_SELLER_SKU'],
                            'productId' => $item['U_LAZ_ITEM_CODE'],
                            'stock' => $item['QuantityOnStock']
                        ];
                        
                    }

                    $count += count($getItems);

                }else{
                    $moreItems = false;
                }

            }

            $lazadaAPI = new LazadaAPIController();
            
            $batch = array_chunk($items,20);
            
            $errorList = [];

            $successCount = 0;

            foreach($batch as $b){

                $skuPayload = [];

                $successList = [];

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

                    $successList[] = [
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

                        $removeDuplicates = [];

                        foreach($updateStock['detail'] as $detail){

                            if(!array_key_exists($detail['seller_sku'],$removeDuplicates)){
                                $removeDuplicates[$detail['seller_sku']] = "Seller SKU / Product ID: ".$detail['seller_sku']." - ".$detail['message'];
                            }
                        
                        }

                        foreach($successList as $key => $value){
                            $sellerSkuExist = array_key_exists($value['sellerSku'], $removeDuplicates);
                            $productIdExist = array_key_exists($value['productId'], $removeDuplicates);
    
                            if($sellerSkuExist == true){
                                
                                unset($successList[$key]);
                            
                            }else if($productIdExist == true){
                                
                                unset($successList[$key]);

                            }
    
                        }

                        foreach($removeDuplicates as $key => $value){

                            $errorList[] = $value;
                        
                        }

                        if(count($successList) > 0){

                            $successCount += count($successList);
                        
                        }
    
                    }
        
                }

            }
            
            if($successCount > 0){
                
                Log::channel('lazada.item_master')->info('Update Stock - Stock updated on '.$successCount.' Lazada SKU/s.');
            
            }
            if(count($errorList) > 0){
               
                Log::channel('lazada.item_master')->error("Update Stock - ".count($errorList)." SKUs have issues while updating the stock: "."\n".implode("\n",$errorList));
            
            }
            if($successCount == 0 && count($errorList) == 0){
                
                Log::channel('lazada.item_master')->warning('Update Stock - No Lazada items available to be updated.');
            
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());

        }
    }
}
