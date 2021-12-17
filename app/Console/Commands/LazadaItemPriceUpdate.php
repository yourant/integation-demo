<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LazadaAPIController;

class LazadaItemPriceUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:item-price-update';

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
                                                    ->where('U_LAZ_INTEGRATION','Y')
                                                    ->where('U_LAZ_ITEM_CODE','!=',null)
                                                    ->where('U_LAZ_SELLER_SKU','!=',null)
                                                    ->skip($count)
                                                    ->get();

                if($getItems->isNotEmpty()){
                    
                    foreach($getItems as $item){

                        $items[] = [
                            'sellerSku' => $item['U_LAZ_SELLER_SKU'],
                            'productId' => $item['U_LAZ_ITEM_CODE'],
                            'origPrice' => $item['U_ORIGINAL_PRICE'],
                            'specialPrice' => $item['ItemPrices']['6']['Price']
                        ];
                        
                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }

            }

            $lazadaAPI = new LazadaAPIController();
            
            $batch = array_chunk($items,20);
            
            $skuPayload = [];
            
            $skuPayloadCount = 0;

            foreach($batch as $b){

                foreach($b as $key){
                        
                    $sellerSku = $key['sellerSku'];
                    $productId = $key['productId'];
                    $origPrice = $key['origPrice'];
                    $specialPrice = $key['specialPrice'];

                    //Create SKU Payload
                    $skuPayload[] = "<Sku>
                                        <ItemId>".$productId."</ItemId>
                                        <SellerSku>".$sellerSku."</SellerSku>
                                        <Price>".$origPrice."</Price>
                                        <SalePrice>".$specialPrice."</SalePrice>
                                    </Sku>";
                
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
                        unset($skuPayload);
                    }
    
                }

            }
            
            if($skuPayloadCount > 0){
                Log::channel('lazada.item_master')->info('Price updated on '.$skuPayloadCount.' Lazada SKU/s.');

            }else{
                Log::channel('lazada.item_master')->warning('No Lazada items available.');

            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());

        }
    }
}
