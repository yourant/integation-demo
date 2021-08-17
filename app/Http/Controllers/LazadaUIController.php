<?php

namespace App\Http\Controllers;

use App\Services\SapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LazadaAPIController;

class LazadaUIController extends Controller
{
    public function index()
    {
        return view('lazada.dashboard');
    }

    public function syncItem()
    {
        try
        {
            //SAP odataClient
            $odataClient = new SapService();
            //Get items with lazada integration set as yes
            $getItems = $odataClient->getOdataClient()->from('Items')
                                                ->where('U_LAZ_INTEGRATION','Yes')
                                                ->get();
            //lazada API
            $lazadaAPI = new LazadaAPIController();

            if(!empty($getItems['0'])){
                //Loop results
                foreach($getItems as $item){
                    //Old and New SKU
                    $oldSku = $item['U_OLD_SKU']; //Old sku from SAP
                    $newSku = $item['ItemCode']; //Sku in lazada
                    $getByNewSku = $lazadaAPI->getProductItem($newSku);
                    
                    if(!empty($getByNewSku['data'])){
                        $lazadaItemId = $getByNewSku['data']['item_id'];
                        
                        $odataClient->getOdataClient()->from('Items')
                                ->whereKey($newSku)
                                ->patch([
                                    'U_LAZ_ITEM_CODE' => $lazadaItemId,
                                ]);
                        
                        Log::channel('lazada.update_price_qty')->info('Items Id updated.');
                    }else if($oldSku != null){
                        $getByOldSku = $lazadaAPI->getProductItem($oldSku);
                        
                        if(!empty($getByOldSku['data'])){
                            $lazadaItemId = $getByOldSku['data']['item_id'];
                            $oldSkuItemCode = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_OLD_SKU',$oldSku)
                                                    ->first();
                            
                            $odataClient->getOdataClient()->from('Items')
                                    ->whereKey($oldSkuItemCode->ItemCode)
                                    ->patch([
                                        'U_LAZ_ITEM_CODE' => $lazadaItemId,
                                    ]);
                            
                            Log::channel('lazada.update_price_qty')->info('Item Id UDFs updated.');
                        }
                    }

                }
            
            }else{
                Log::channel('lazada.update_price_qty')->warning('No Lazada items available.');
            }
        } catch (\Exception $e) {
            Log::channel('lazada.update_price_qty')->emergency($e->getMessage());
        }
    }

    public function updatePrice()
    {
        try
        {
            //SAP odataClient
            $odataClient = new SapService();
            //Get items with lazada integration set as yes
            $getItems = $odataClient->getOdataClient()->from('Items')
                                                 ->where('U_LAZ_INTEGRATION','Yes')
                                                 ->get();
            //lazada API
            $lazadaAPI = new LazadaAPIController();
            
            if(!empty($getItems['0'])){
                //Loop results
                foreach($getItems as $item){
                    //Price
                    $sapPrice = $item['ItemPrices']['8']['Price'];
                    //Old and New SKU
                    $oldSku = $item['U_OLD_SKU']; //Old sku from SAP
                    $newSku = $item['ItemCode']; //Sku in lazada
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
                    //Run 
                    $lazadaAPI->updatePriceQuantity($finalPayload);
                    
                    Log::channel('lazada.update_price_qty')->info('Items stock updated.');

                }else{
                    Log::channel('lazada.update_price_qty')->info('No Items stock to be updated.');
                }
            }
        } catch (\Exception $e) {
            Log::channel('lazada.update_price_qty')->emergency($e->getMessage());
        }

    }

    public function updateStock()
    {
        try
        {
            //SAP odataClient
            $odataClient = new SapService();
            //Get items with lazada integration set as yes
            $getItems = $odataClient->getOdataClient()->from('Items')
                                                 ->where('U_LAZ_INTEGRATION','Yes')
                                                 ->get();
            //lazada API
            $lazadaAPI = new LazadaAPIController();

            if(!empty($getItems['0'])){
                //Loop results
                foreach($getItems as $item){
                    //Stocks
                    $sapStock = $item['QuantityOnStock'];
                    //Old and New SKU
                    $oldSku = $item['U_OLD_SKU']; //Old sku from SAP
                    $newSku = $item['ItemCode']; //Sku in lazada
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

                if(!empty($skuPayload)){
                    $finalPayload = "<Request>
                                        <Product>
                                            <Skus>
                                                ".implode('',$skuPayload)."
                                            </Skus>
                                        </Product>
                                    </Request>";
                    //Run 
                    $lazadaAPI->updatePriceQuantity($finalPayload);
                    
                    Log::channel('lazada.update_price_qty')->info('Items price updated.');

                }else{
                    Log::channel('lazada.update_price_qty')->info('No Items price to be updated.');
                }
            }

        } catch (\Exception $e) {
            Log::channel('lazada.update_price_qty')->emergency($e->getMessage());
        }
    }
}
