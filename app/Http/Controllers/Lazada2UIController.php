<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use App\Models\AccessToken;
use App\Services\SapService;
use Illuminate\Http\Request;
use App\Services\Lazada2Service;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Lazada2APIController;

class Lazada2UIController extends Controller
{
    public function index()
    {
        return view('lazada.dashboard2');
    }

    public function refreshToken()
    {
        try
        {
            $lazadaToken = AccessToken::where('platform','lazada2')->first();
            //Lazada Service
            $lazService = new Lazada2Service();
            //Lazada SDK
            $lazClient = new LazopClient($lazService->getAppUrl(),$lazService->getAppKey(),$lazService->getAppSecret());
            $lazRequest = new LazopRequest('/auth/token/refresh');
            $lazRequest->addApiParam('refresh_token',$lazadaToken->refresh_token);
            $response = json_decode($lazClient->execute($lazRequest));
            //Update Token
            $updatedToken = AccessToken::where('platform', 'lazada2')
                    ->update([
                        'refresh_token' => $response->refresh_token,
                        'access_token' => $response->access_token
                    ]);

            if($updatedToken) {
                Log::channel('lazada2.refresh_token')->info('New tokens generated.');
            } else {
                Log::channel('lazada2.refresh_token')->info('Problem while generating tokens.');
            }
            
        }

        catch(\Exception $e)
        {
            Log::channel('lazada2.refresh_token')->emergency($e->getMessage());
        }
    }

    public function syncItem()
    {
        try
        {
            //SAP odataClient
            $odataClient = new SapService();
            //Get items with lazada integration set as yes
            $getItems = $odataClient->getOdataClient()->from('Items')
                                                ->where('U_LAZ2_INTEGRATION','Yes')
                                                ->get();
            //lazada API
            $lazadaAPI = new Lazada2APIController();

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
                                    'U_LAZ2_ITEM_CODE' => $lazadaItemId,
                                ]);
                        
                        Log::channel('lazada2.update_price_qty')->info('Items Id updated.');
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
                                        'U_LAZ2_ITEM_CODE' => $lazadaItemId,
                                    ]);
                            
                            Log::channel('lazada2.update_price_qty')->info('Item Id UDFs updated.');
                        }
                    }

                }
            
            }else{
                Log::channel('lazada2.update_price_qty')->warning('No Lazada items available.');
            }
        } catch (\Exception $e) {
            Log::channel('lazada2.update_price_qty')->emergency($e->getMessage());
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
                                                 ->where('U_LAZ2_INTEGRATION','Yes')
                                                 ->get();
            //lazada API
            $lazadaAPI = new Lazada2APIController();
            
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
                    
                    Log::channel('lazada2.update_price_qty')->info('Items price updated.');

                }else{
                    Log::channel('lazada2.update_price_qty')->info('No Items price to be updated.');
                }
            }
        } catch (\Exception $e) {
            Log::channel('lazada2.update_price_qty')->emergency($e->getMessage());
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
                                                 ->where('U_LAZ2_INTEGRATION','Yes')
                                                 ->get();
            //lazada API
            $lazadaAPI = new Lazada2APIController();

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
                    
                    Log::channel('lazada2.update_price_qty')->info('Items stock updated.');

                }else{
                    Log::channel('lazada2.update_price_qty')->info('No Items stock to be updated.');
                }
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.update_price_qty')->emergency($e->getMessage());
        }
    }

    
}
