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

                $getItems = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_LAZ2_INTEGRATION','Y')
                                                    ->where('U_LAZ2_ITEM_CODE','!=',null)
                                                    ->where('U_LAZ2_SELLER_SKU','!=',null)
                                                    ->skip($count)
                                                    ->get();

                if($getItems->isNotEmpty()){
                    
                    foreach($getItems as $item){

                        $items[] = [
                            'sellerSku' => $item['U_LAZ2_SELLER_SKU'],
                            'productId' => $item['U_LAZ2_ITEM_CODE'],
                            'origPrice' => $item['U_ORIGINAL_PRICE'],
                            'specialPrice' => $item['ItemPrices']['7']['Price']
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
                    $origPrice = $key['origPrice'];
                    $specialPrice = $key['specialPrice'];

                    if($specialPrice == 0){
                        //Create SKU Payload
                        $skuPayload[] = "<Sku>
                                            <ItemId>".$productId."</ItemId>
                                            <SellerSku>".$sellerSku."</SellerSku>
                                            <Price>".$origPrice."</Price>
                                        </Sku>";
                    }else{
                        //Create SKU Payload
                        $skuPayload[] = "<Sku>
                                            <ItemId>".$productId."</ItemId>
                                            <SellerSku>".$sellerSku."</SellerSku>
                                            <Price>".$origPrice."</Price>
                                            <SalePrice>".$specialPrice."</SalePrice>
                                        </Sku>";
                    }

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
                    $updatePrice = $lazadaAPI->updatePriceQuantity($finalPayload);
    
                    if($updatePrice['code'] == 0){
                        $successCount += count($skuPayload);
                    }else{

                        foreach($itemList as $item){
                        
                            $sellerSkuExist = array_search($item['sellerSku'], array_column($updatePrice['detail'],'seller_sku'));
                            $productIdExist = array_search($item['productId'], array_column($updatePrice['detail'],'seller_sku'));
    
                            if($sellerSkuExist !== false || $productIdExist !== false){
                                $errorCount++;
                            }else{
                                $successCount++;
                            }
    
                        }

                        foreach($updatePrice['detail'] as $detail){
                            $errorList[] = "Seller SKU / Product ID: ".$detail['seller_sku']." - ".$detail['message'];
                        }
                    }
    
                }

            }
            
            if($successCount > 0){
                
                Log::channel('lazada2.item_master')->info('Update Price - Price updated on '.$successCount.' Lazada SKU/s.');
            
            }
            if($errorCount > 0){
                
                Log::channel('lazada2.item_master')->error("Update Price - ".$errorCount." SKUs have issues while updating the price: "."\n".implode("\n",$errorList));
            
            }
            if($successCount == 0 && $errorCount == 0){
                Log::channel('lazada2.item_master')->warning('Update Price - No Lazada items available to be updated.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'Update Price - No Lazada items available to be updated.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.item_master')->emergency($e->getMessage());

        }
    }
}
