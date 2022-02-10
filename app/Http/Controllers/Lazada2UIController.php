<?php

namespace App\Http\Controllers;

use DateTime;
use LazopClient;
use LazopRequest;
use App\Models\AccessToken;
use App\Services\SapService;
use Illuminate\Http\Request;
use App\Services\Lazada2Service;
use App\Services\LazadaLogService;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;
use App\Http\Controllers\Lazada2APIController;

class Lazada2UIController extends Controller
{
    public function index()
    {
        return view('lazada.dashboard2');
    }

    public function displayTokenStatus()
    {
        $lazadaToken = AccessToken::where('platform','lazada2')->first();
        $currentDate = new DateTime('now');
        $expiryDate = new DateTime($lazadaToken->updated_at);
        $expiryDate = $expiryDate->modify('+29 days'); //28 + 1
        $days = $currentDate->diff($expiryDate)->format('%r%a');

        return response()->json($days);

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
                Log::channel('lazada2.refresh_token')->info('New tokens generated. Please reload the page.');

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => 'New tokens generated. Please reload the page.'
                ]);

            } else {
                Log::channel('lazada2.refresh_token')->info('Problem while generating tokens.');

                return response()->json([
                    'title' => 'Error: ',
                    'status' => 'alert-danger',
                    'message' => 'Problem while generating tokens.'
                ]);

            }
            
        }

        catch(\Exception $e)
        {
            Log::channel('lazada2.refresh_token')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function itemMasterIntegration()
    {
        try {
            $odataClient = new SapService();
            
            $brand = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_BRAND')->first();
            $primaryCategory = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_CATEGORY')->first();
            $deliveryOption = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_DELIVERY_OPTION')->first();
            $packageHeight = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_HEIGHT')->first();
            $packageLength = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_LENGTH')->first();
            $packageWeight = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_WEIGHT')->first();
            $packageWidth = $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_PACKAGE_WIDTH')->first();
            $warrantyType= $odataClient->getOdataClient()->from('U_LDD')->where('Code','L_DFLT_WARRANTY_TYPE')->first();
            
            $count = 0;

            $moreItems = true;

            $items = [];

            $errorList = [];

            $addedList = [];

            $updatedList = [];

            while($moreItems){
                $getItems = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_LAZ2_INTEGRATION','Y')
                                                    ->where('U_LAZ2_ITEM_CODE',null)
                                                    ->skip($count)
                                                    ->get();

                if($getItems->isNotEmpty()){

                    foreach($getItems as $item){

                        $items[] = [
                            'itemName' => $item['ItemName'],
                            'sellerSku' => $item['ItemCode'],
                            'quantity' => $item['QuantityOnStock'],
                            'price' => $item['U_ORIGINAL_PRICE'],
                            'status' => $item['Valid']
                        ];
                    
                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }
            }

            foreach($items as $item){

                $createProductPayload = "
                    <Request>
                        <Product>
                            <PrimaryCategory>".$primaryCategory->U_VALUE."</PrimaryCategory>
                            <Attributes>
                                <name>".$item['itemName']."</name>
                                <brand>".$brand->U_VALUE."</brand>
                                <delivery_option_sof>".$deliveryOption->U_VALUE."</delivery_option_sof>
                                <warranty_type>".$warrantyType->U_VALUE."</warranty_type>
                            </Attributes>
                            <Skus>
                                <Sku>
                                    <SellerSku>".$item['sellerSku']."</SellerSku>
                                    <quantity>".$item['quantity']."</quantity>
                                    <price>".$item['price']."</price>
                                    <package_length>".$packageLength->U_VALUE."</package_length>
                                    <package_height>".$packageHeight->U_VALUE."</package_height>
                                    <package_weight>".$packageWeight->U_VALUE."</package_weight>
                                    <package_width>".$packageWidth->U_VALUE."</package_width>
                                </Sku>
                            </Skus>
                        </Product>
                    </Request>";
                
                $lazadaAPI = new Lazada2APIController();
                
                $response = $lazadaAPI->createProduct($createProductPayload);

                if(!empty($response['data'])){
                    $itemId = $response['data']['item_id'];
                    $sellerSku = $response['data']['sku_list']['0']['seller_sku'];
                    $update = $odataClient->getOdataClient()->from('Items')
                                ->whereKey($item['sellerSku'])
                                ->patch([
                                    'U_LAZ2_ITEM_CODE' => $itemId,
                                    'U_LAZ2_SELLER_SKU' => $sellerSku
                                ]);
                    
                    ($update ? $addedList[] = $item['sellerSku'] : '');
                
                }else{

                    if($response['code'] != "500" && !empty($response['detail'])){
                        //Price error
                        $errorList[] = $item['sellerSku'].' - '.$response['detail']['0']['message'];

                    }
                    else if($response['code'] != "500" && empty($response['detail'])){
                        //Price error
                        $errorList[] = $item['sellerSku'].' - '.$response['message'];
                    }
                    else if($response['code'] == "500"){

                        if($item['status'] == 'tYES'){
                            $payload = "<Request>
                                                <Product>
                                                    <Skus>
                                                        <Sku>
                                                            <SellerSku>".$item['sellerSku']."</SellerSku>
                                                            <Status>active</Status>
                                                        </Sku>
                                                    </Skus>
                                                </Product>
                                            </Request>";
                            $activate = $lazadaAPI->activateProduct($payload);
    
                            if($activate['code'] == '0'){
                                
                                $updatedList[] = $item['sellerSku'];

                            }
                            else if($activate['code'] != "500" && !empty($activate['detail'])){
                                //Price error
                                $errorList[] = $item['sellerSku'].' - '.$activate['detail']['0']['message'];
        
                            }
                            else if($activate['code'] != "500" && empty($activate['detail'])){
                                //Price error
                                $errorList[] = $item['sellerSku'].' - '.$activate['message'];
                            }
    
                        }else if($item['status'] == 'tNO'){
                            $getDetail = $lazadaAPI->getProductItem($item['sellerSku']);
                            $itemId = $getDetail['data']['item_id'];
                            $payload = "<Request>
                                            <Product>
                                                <ItemId>".$itemId."</ItemId>
                                                <Skus>
                                                    <SellerSku>".$item['sellerSku']."</SellerSku>
                                                </Skus>
                                            </Product>
                                        </Request>";
                            $deactivate = $lazadaAPI->deactivateProduct($payload);
    
                            if($deactivate['code'] == '0'){

                                $updatedList[] = $item['sellerSku'];
                            
                            }
                            else if($deactivate['code'] != "500" && !empty($deactivate['detail'])){
                                //Price error
                                $errorList[] = $item['sellerSku'].' - '.$deactivate['detail']['0']['message'];
        
                            }
                            else if($deactivate['code'] != "500" && empty($deactivate['detail'])){
                                //Price error
                                $errorList[] = $item['sellerSku'].' - '.$deactivate['message'];
                            }
                            
                        }

                    }
                    

                }
            }

            $successMessage = null;
            
            $errorMessage = null;

            if(count($addedList) > 0 && count($updatedList) == 0){
                
                Log::channel('lazada2.item_master')->info('Create Product - '.count($addedList).' new product/s added: '.implode(",",$addedList));
                
                $successMessage = count($addedList).' new product/s added: '.implode(",",$addedList);
            
            }else if(count($addedList) == 0 && count($updatedList) > 0){
                
                Log::channel('lazada2.item_master')->info('Create Product - '.count($updatedList).' SKU/s status updated: '.implode(",",$updatedList));
                
                $successMessage = count($updatedList).' SKU/s status updated: '.implode(",",$updatedList);
            
            }else if(count($addedList) > 0 && count($updatedList) > 0){
                
                Log::channel('lazada2.item_master')->info('Create Product - '.count($addedList).' new product/s added: '.implode(",",$addedList));

                Log::channel('lazada2.item_master')->info('Create Product - '.count($updatedList).' SKU/s status updated: '.implode(",",$updatedList));
                
                $successMessage = count($addedList).' new product/s added: '.implode(",",$addedList)."\n".count($updatedList).' SKU/s status updated: '.implode(",",$updatedList);
            
            }

            if(count($errorList) > 0){

                Log::channel('lazada2.item_master')->error('Create Product - '.count($errorList). ' SKU/s have problems: '."\n".implode("\n",$errorList));

                $errorMessage = count($errorList). ' SKU/s have problems. Please check the logs for further details.';
            }

            $success = array(
                'success_title' => 'Success: ',
                'success_message' => $successMessage
            );

            $danger = array(
                'danger_title' => 'Error: ',
                'danger_message' => $errorMessage
            );
            
            
            if($successMessage != null && $errorMessage != null){

                return response()->json(array_merge($success,$danger));
            
            }else if($successMessage != null && $errorMessage == null){
                
                return response()->json($success);

            }else if($successMessage == null && $errorMessage != null){

               return response()->json($danger);

            }else{

                Log::channel('lazada2.item_master')->info('No new Lazada products to be added.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No new Lazada products to be added.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.item_master')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function syncItem()
    {
        try
        {
            $odataClient = new SapService();
            
            $count = 0;

            $itemCount = 0;

            $moreItems = true;
            
            $items = [];
            
            while($moreItems){
            
                $getItems = $odataClient->getOdataClient()
                                ->from('Items')
                                ->where('U_LAZ2_INTEGRATION','Y')
                                ->where('U_LAZ2_ITEM_CODE',null)
                                ->where('U_LAZ2_SELLER_SKU',null)
                                ->skip($count)
                                ->get();
                
                if($getItems->isNotEmpty()){
                    
                    foreach($getItems as $item){
                        
                        $items[] = [
                            'itemCode' => $item['ItemCode'],
                            'oldSku' => $item['U_MPS_OLDSKU']
                        ];
                        
                    }
    
                    $count += count($getItems);
    
                }else{
                    $moreItems = false;
                }
    
            }

            if(!empty($items)){
                
                $lazadaAPI = new Lazada2APIController();
                $batch = array_chunk($items,50);
                $oldSkus = [];
                $itemCodes = [];
                
                foreach($batch as $b){
                 
                    foreach($b as $key){

                        array_push($itemCodes,$key['itemCode']);
    
                        if($key['oldSku'] != null){
                            array_push($oldSkus,$key['oldSku']);
                        }
                    }
    
                    if(!empty($itemCodes)){
                        $skus =  '['.'"'.implode('","',$itemCodes).'"'.']';
                        $response = $lazadaAPI->getProducts($skus);
                        $resultArray = [];
                        
                        if(!empty($response['data']['products'])){

                            foreach($response['data']['products'] as $product){
                            
                                foreach($product['skus'] as $sku){
                                    
                                    $key = array_search($sku['SellerSku'],$itemCodes);
    
                                    if($key === false){
                                        //Not Found
                                    }else{
    
                                        $update = $odataClient->getOdataClient()->from('Items')
                                                                            ->whereKey($itemCodes[$key])
                                                                            ->patch([
                                                                                'U_LAZ2_ITEM_CODE' => $product['item_id'],
                                                                                'U_LAZ2_SELLER_SKU' => $sku['SellerSku']
                                                                            ]);
                                        ($update ? $itemCount ++ : '');
    
                                    }
                                    
                                }
                            
                            }
                        }
                        
                    }

                    if(!empty($oldSkus)){
                        $skus =  '['.'"'.implode('","',$oldSkus).'"'.']';
                        $response = $lazadaAPI->getProducts($skus);
                        $resultArray = [];

                        if(!empty($response['data']['products'])){

                            foreach($response['data']['products'] as $product){
        
                                foreach($product['skus'] as $sku){
                                    
                                    $key = array_search($sku['SellerSku'],$oldSkus);

                                    if($key === false){
                                        //Not Found
                                    }else{

                                        $get = $odataClient->getOdataClient()->from('Items')
                                                        ->where('U_MPS_OLDSKU',$oldSkus[$key])
                                                        ->first();

                                        $update = $odataClient->getOdataClient()->from('Items')
                                                                            ->whereKey($get->ItemCode)
                                                                            ->patch([
                                                                                'U_LAZ2_ITEM_CODE' => $product['item_id'],
                                                                                'U_LAZ2_SELLER_SKU' => $sku['SellerSku']
                                                                            ]);
                                        ($update ? $itemCount ++ : '');

                                    }
                                    
                                }
                            
                            }
                        }
                    }
    
                }
            }

            if($itemCount > 0){
                Log::channel('lazada2.item_master')->info($itemCount.' Item UDFs updated.');

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => $itemCount.' Item UDFs updated.'
                ]);

            }else{
                Log::channel('lazada2.item_master')->warning('No new Lazada items to be sync.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No new Lazada items to be sync.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.item_master')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function updatePrice()
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

        $successCount = 0;

        foreach($batch as $b){

            $skuPayload = [];

            $successList = [];

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
                $updatePrice = $lazadaAPI->updatePriceQuantity($finalPayload);

                if($updatePrice['code'] == 0){

                    $successCount += count($skuPayload);
                
                }else{

                    $removeDuplicates = [];

                    foreach($updatePrice['detail'] as $detail){

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
            Log::channel('lazada2.item_master')->info('Update Price - Price updated on '.$successCount.' Lazada SKU/s.');
        }
        
        if(count($errorList) > 0){
            Log::channel('lazada2.item_master')->error("Update Price - ".count($errorList)." SKUs have issues while updating the price: "."\n".implode("\n",$errorList));
        }

        $success = array(
            'success_title' => 'Success: ',
            'success_message' => 'Price updated on <b>'.$successCount.'</b> Lazada SKU/s.'
        );

        $danger = array(
            'danger_title' => 'Error: ',
            'danger_message' => 'Problems encountered while updating the price of <b>'.count($errorList).'</b> SKU/s. Please check the logs for further details.'
        );

        if($successCount > 0 && count($errorList) > 0){
            
            return response()->json(array_merge($success,$danger));
        
        }else if($successCount > 0 && count($errorList) == 0){

            return response()->json($success);

        }else if($successCount == 0 && count($errorList) > 0){

            return response()->json($danger);
        }else{

            Log::channel('lazada2.item_master')->warning('Update Price - No Lazada items available to be updated.');

            return response()->json([
                'title' => 'Information: ',
                'status' => 'alert-info',
                'message' => 'Update Price - No Lazada items available to be updated.'
            ]);
        }
        
    }

    public function updateStock()
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
            Log::channel('lazada2.item_master')->info('Update Stock - Stock updated on '.$successCount.' Lazada SKU/s.');
        }

        if(count($errorList) > 0){
            Log::channel('lazada2.item_master')->error("Update Stock - ".count($errorList)." SKUs have issues while updating the stock: "."\n".implode("\n",$errorList));
        }

        $success = array(
            'success_title' => 'Success: ',
            'success_message' => 'Stock updated on <b>'.$successCount.'</b> Lazada SKU/s.'
        );

        $danger = array(
            'danger_title' => 'Error: ',
            'danger_message' => 'Problems encountered while updating the stock of <b>'.count($errorList).'</b> SKU/s. Please check the logs for further details.'
        );

        if($successCount > 0 && count($errorList) > 0){
            
            return response()->json(array_merge($success,$danger));
        
        }else if($successCount > 0 && count($errorList) == 0){

            return response()->json($success);

        }else if($successCount == 0 && count($errorList) > 0){

            return response()->json($danger);
        }else{

            Log::channel('lazada2.item_master')->warning('Update Stock - No Lazada items available to be updated.');

            return response()->json([
                'title' => 'Information: ',
                'status' => 'alert-info',
                'message' => 'Update Stock - No Lazada items available to be updated.'
            ]);
        }

        
    }

    public function generateSalesOrder()
    {
        
        $odataClient = new SapService();
        $lazadaLog = new LazadaLogService('lazada2.sales_order');

        $lazadaCustomer = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','LAZADA2_CUSTOMER')->first();
        $taxCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','TAX_CODE')->first();
        $percentage = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','PERCENTAGE')->first();
        $whsCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','WAREHOUSE_CODE')->first();

        $lazadaAPI = new Lazada2APIController();

        $moreOrders= true;
        
        $offset = 0;

        $orderIdArray = [];
        

        while($moreOrders){
            
            $orders = $lazadaAPI->getPendingOrders($offset);

            if(!empty($orders['data']['orders'])){
            
                foreach($orders['data']['orders'] as $order){
                    $orderId = $order['order_id'];
                    array_push($orderIdArray,$orderId);
                    //Basic Information
                    $customerName = $order['customer_first_name'].' '.$order['customer_last_name'];
                    $receiverPhone = $order['address_shipping']['phone'];
                    //Shipping Address
                    $sName = $order['address_shipping']['first_name'] .' '. $order['address_shipping']['last_name'];
                    $sPhone = $order['address_shipping']['phone'];
                    $sAddress = $order['address_shipping']['address1'];
                    $sPostCode = $order['address_shipping']['post_code'];
                    $sCountry = $order['address_shipping']['country'];
                    $shippingAddress = $sName."\n".$sPhone."\n".$sAddress.', '.$sPostCode.', '.$sCountry;
                    //Billing Address
                    $bName = $order['address_billing']['first_name'] .' '. $order['address_billing']['last_name'];
                    $bPhone = $order['address_billing']['phone'];
                    $bAddress = $order['address_billing']['address1'];
                    $bPostCode = $order['address_billing']['post_code'];
                    $bCountry = $order['address_billing']['country'];
                    $billingAddress = $bName."\n".$bPhone."\n".$bAddress.', '.$bPostCode.', '.$bCountry;

                    $tempSO[$orderId] = [
                        'CardCode' => $lazadaCustomer->Name,
                        'DocDate' => substr($order['created_at'],0,10),
                        'DocDueDate' => substr($order['created_at'],0,10),
                        'TaxDate' => substr($order['created_at'],0,10),
                        'NumAtCard' => $orderId,
                        'U_Ecommerce_Type' => 'Lazada_2',
                        'U_Order_ID' => $orderId,
                        'U_Basic_Information' => 'Customer Name: '.$customerName."\n".'Receiver Phone: '.$receiverPhone,
                        'U_Shipping_Address' => $shippingAddress,
                        'U_Billing_Address' => $billingAddress,
                        'DocTotal' => $order['price']
                    ];

                }

                if($orders['data']['count'] == $orders['data']['countTotal']){
                    $moreOrders = false;
                }else{  
                    $offset += $orders['data']['count'];
                }
            
            }else{
                $moreOrders = false;
            }
        
        }

        if(!empty($orderIdArray)){
            
            $orderIds = '['.implode(',',$orderIdArray).']';
            $orderItems = $lazadaAPI->getMultipleOrderItems($orderIds);
            $counter = 0;
            $errorOrders = [];
            
            foreach ($orderItems['data'] as $item) {
                $orderId = $item['order_id'];
    
                foreach($item['order_items'] as $orderItem){

                    try {
                        $result = $odataClient->getOdataClient()->from('Items')
                                            ->select('ItemCode','ItemName')
                                            ->where('U_LAZ2_SELLER_SKU',$orderItem['sku'])
                                            ->first();
                    } catch (ClientException $e) {
                        $msg = "Item ".$orderItem['sku']." on order ".$orderId." has problem".
                        $lazadaLog->writeSapLog($e,$msg);
                    }

                    if(isset($result)){
                        $items[$orderId][] = [
                            'ItemCode' => $result->ItemCode,
                            'Quantity' => 1,
                            'VatGroup' => $taxCode->Name,
                            'UnitPrice' => $orderItem['item_price'] / $percentage->Name,
                            'WarehouseCode' => $whsCode->Name
                        ];
                    }
                    
                }

                if(isset($items[$orderId])){
                    $tempSO[$orderId]['DocumentLines'] = $items[$orderId];
                }

            }
            
            foreach($tempSO as $key => $value){
                $finalSO = array_slice($tempSO[$key],0);
                $getSO = $odataClient->getOdataClient()->from('Orders')
                                ->where('U_Order_ID',(string)$finalSO['U_Order_ID'])
                                ->where('U_Ecommerce_Type','Lazada_2')
                                ->where(function($query){
                                    $query->where('DocumentStatus','bost_Open');
                                    $query->orWhere('DocumentStatus','bost_Close');
                                })
                                ->where('Cancelled','tNO')
                                ->first();

                if(!$getSO){

                    try {
                        $odataClient->getOdataClient()->post('Orders',$finalSO);

                        $counter++;

                        Log::channel('lazada2.sales_order')->info('Sales order for Lazada order:'.$finalSO['U_Order_ID'].' created successfully.');

                    } catch (ClientException $e) {
                        $msg = "Order ".$finalSO['U_Order_ID']." has problems";
                        $lazadaLog->writeSapLog($e,$msg);

                        array_push($errorOrders,$finalSO['U_Order_ID']);
                    }

                }else{
                    unset($finalSO);
                }

            }
            
            $errors = implode(", ",$errorOrders);
        
            $success = array(
                'success_title' => 'Success: ',
                'success_message' => '<b>'.$counter.'</b> New Sales Orders Generated.',
            );

            $danger = array(
                'danger_title' => 'Error: ',
                'danger_message' => 'Problems encountered while generating <b>'.count($errorOrders).'</b> order/s: '.$errors.'.'
            );
            
            if($counter > 0 && count($errorOrders) > 0){

                return response()->json(array_merge($success,$danger));

            }else if($counter > 0 && count($errorOrders) == 0){

                return response()->json($success);

            }else if($counter == 0 && count($errorOrders) > 0){

                return response()->json($danger);

            }else{

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'Pending orders were already generated.'
                ]);
            }

        }else{

            return response()->json([
                'title' => 'Information: ',
                'status' => 'alert-info',
                'message' => 'No pending orders for now.'
            ]);

        }
        
    }

    public function generateInvoice()
    {
        
        $odataClient = new SapService();

        $lazadaLog = new LazadaLogService('lazada2.ar_invoice');
    
        $lazadaAPI = new Lazada2APIController();
        
        $offset = 0;
        
        $moreOrders= true;

        $orderArray = [];

        $customerInfo = [];

        while($moreOrders){

            $orders = $lazadaAPI->getReadyToShipOrders($offset);

            if(!empty($orders['data']['orders'])){
                foreach($orders['data']['orders'] as $order){
                    $orderId = $order['order_id'];
                    array_push($orderArray,$orderId);
                    //Basic Information
                    $customerName = $order['customer_first_name'].' '.$order['customer_last_name'];
                    $receiverPhone = $order['address_shipping']['phone'];
                    //Shipping Address
                    $sName = $order['address_shipping']['first_name'] .' '. $order['address_shipping']['last_name'];
                    $sPhone = $order['address_shipping']['phone'];
                    $sAddress = $order['address_shipping']['address1'];
                    $sPostCode = $order['address_shipping']['post_code'];
                    $sCountry = $order['address_shipping']['country'];
                    $shippingAddress = $sName."\n".$sPhone."\n".$sAddress.', '.$sPostCode.', '.$sCountry;
                    //Billing Address
                    $bName = $order['address_billing']['first_name'] .' '. $order['address_billing']['last_name'];
                    $bPhone = $order['address_billing']['phone'];
                    $bAddress = $order['address_billing']['address1'];
                    $bPostCode = $order['address_billing']['post_code'];
                    $bCountry = $order['address_billing']['country'];
                    $billingAddress = $bName."\n".$bPhone."\n".$bAddress.', '.$bPostCode.', '.$bCountry;
                    
                    $customerInfo[$orderId] = [
                        'U_Basic_Information' => 'Customer Name: '.$customerName."\n".'Receiver Phone: '.$receiverPhone,
                        'U_Shipping_Address' => $shippingAddress,
                        'U_Billing_Address' => $billingAddress,
                    ];
                }

                if($orders['data']['count'] == $orders['data']['countTotal']){
                    $moreOrders = false;
                }else{  
                    $offset += $orders['data']['count'];
                }
            
            }else{
                $moreOrders = false;
            }
        
        }

        if(!empty($orderArray)){

            $counter = 0;
            $errorOrders = [];
            
            foreach($orderArray as $id){
                $orderDocEntry = $odataClient->getOdataClient()->select('DocEntry')->from('Orders')
                                    ->where('U_Order_ID',(string)$id)
                                    ->where('U_Ecommerce_Type','Lazada_2')
                                    ->where('DocumentStatus','bost_Open')
                                    ->where('Cancelled','tNO')
                                    ->first();
                $getInv = $odataClient->getOdataClient()->from('Invoices')
                                    ->where('U_Order_ID',(string)$id)
                                    ->where('U_Ecommerce_Type','Lazada_2')
                                    ->where(function($query){
                                        $query->where('DocumentStatus','bost_Open');
                                        $query->orWhere('DocumentStatus','bost_Close');
                                    })
                                    ->where('Cancelled','tNO')
                                    ->first();

                if($orderDocEntry && !$getInv){
                    $getSO = $odataClient->getOdataClient()->from('Orders')->find($orderDocEntry['DocEntry']);
                    $items = [];
                    foreach ($getSO['DocumentLines'] as $key => $value) {
                        $batchList = [];
                        if($value['BatchNumbers']) {                  
                            foreach ($value['BatchNumbers'] as $batch) {
                                $batchList[] = [
                                    'BatchNumber' => $batch['BatchNumber'],
                                    'Quantity' => $batch['Quantity']
                                ];
                            }
                        }
    
                        $items[] = [
                            'BaseType' => 17,
                            'BaseEntry' => $getSO['DocEntry'],
                            'BaseLine' => $key,
                            'BatchNumbers' => $batchList
                        ];
                    }

                    try {
                        //Copy sales order to invoice
                        $odataClient->getOdataClient()->post('Invoices',[
                            'CardCode' => $getSO['CardCode'],
                            'DocDate' => $getSO['DocDate'],
                            'DocDueDate' => $getSO['DocDueDate'],
                            'PostingDate' => $getSO['TaxDate'],
                            'NumAtCard' => $getSO['NumAtCard'],
                            'U_Ecommerce_Type' => $getSO['U_Ecommerce_Type'],
                            'U_Order_ID' => $getSO['U_Order_ID'],
                            'U_Basic_Information' =>  $customerInfo[$getSO['U_Order_ID']]['U_Basic_Information'],
                            'U_Shipping_Address' => $customerInfo[$getSO['U_Order_ID']]['U_Shipping_Address'],
                            'U_Billing_Address' => $customerInfo[$getSO['U_Order_ID']]['U_Billing_Address'],
                            'DocumentLines' => $items 
                        ]);
                        
                        $counter++;
                        
                        Log::channel('lazada2.ar_invoice')->info('A/R invoice for Lazada order:'.$getSO['U_Order_ID'].' created successfully.');

                    } catch (ClientException $e) {
                        $msg = "Order ".$getSO['U_Order_ID']." has problems";
                    
                        $lazadaLog->writeSapLog($e,$msg);

                        array_push($errorOrders,$getSO['U_Order_ID']);
                    }

                }
                
            }

            $errors = implode(", ",$errorOrders);
        
            $success = array(
                'success_title' => 'Success: ',
                'success_message' => '<b>'.$counter.'</b> New A/R Invoices Generated.',
            );

            $danger = array(
                'danger_title' => 'Error: ',
                'danger_message' => 'Problems encountered while generating <b>'.count($errorOrders).'</b> order/s: '.$errors.'.'
            );
            
            if($counter > 0 && count($errorOrders) > 0){

                return response()->json(array_merge($success,$danger));

            }else if($counter > 0 && count($errorOrders) == 0){

                return response()->json($success);

            }else if($counter == 0 && count($errorOrders) > 0){

                return response()->json($danger);
            
            }else{

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'Ready to ship orders were already generated.'
                ]);

            }

        }else{

            return response()->json([
                'title' => 'Information: ',
                'status' => 'alert-info',
                'message' => 'No ready to ship orders for now.'
            ]);

        }
        
    }

    public function generateCreditMemo()
    {

        $odataClient = new SapService();
        $lazadaLog = new LazadaLogService('lazada2.credit_memo');

        $lazadaCustomer = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','LAZADA2_CUSTOMER')->first();
        $taxCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','TAX_CODE')->first();
        $percentage = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','PERCENTAGE')->first();
        $whsCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','WAREHOUSE_CODE')->first();

        $lazadaAPI = new Lazada2APIController();
        
        $moreOrders= true;
        
        $offset = 0;

        $orderIdArray = [];
        
        while($moreOrders){

            $orders = $lazadaAPI->getReturnedOrders($offset);
        
            if(!empty($orders['data']['orders'])){

                foreach($orders['data']['orders'] as $order){
                    $orderId = $order['order_id'];
                    array_push($orderIdArray,$orderId);
                    //Basic Information
                    $customerName = $order['customer_first_name'].' '.$order['customer_last_name'];
                    $receiverPhone = $order['address_shipping']['phone'];
                    //Shipping Address
                    $sName = $order['address_shipping']['first_name'] .' '. $order['address_shipping']['last_name'];
                    $sPhone = $order['address_shipping']['phone'];
                    $sAddress = $order['address_shipping']['address1'];
                    $sPostCode = $order['address_shipping']['post_code'];
                    $sCountry = $order['address_shipping']['country'];
                    $shippingAddress = $sName."\n".$sPhone."\n".$sAddress.', '.$sPostCode.', '.$sCountry;
                    //Billing Address
                    $bName = $order['address_billing']['first_name'] .' '. $order['address_billing']['last_name'];
                    $bPhone = $order['address_billing']['phone'];
                    $bAddress = $order['address_billing']['address1'];
                    $bPostCode = $order['address_billing']['post_code'];
                    $bCountry = $order['address_billing']['country'];
                    $billingAddress = $bName."\n".$bPhone."\n".$bAddress.', '.$bPostCode.', '.$bCountry;
                        
                    $tempCM[$orderId] = [
                        'CardCode' => $lazadaCustomer->Name,
                        'DocDate' => substr($order['created_at'],0,10),
                        'DocDueDate' => substr($order['created_at'],0,10),
                        'TaxDate' => substr($order['created_at'],0,10),
                        'NumAtCard' => $orderId,
                        'U_Ecommerce_Type' => 'Lazada_2',
                        'U_Order_ID' => $orderId,
                        'U_Basic_Information' => 'Customer Name: '.$customerName."\n".'Receiver Phone: '.$receiverPhone,
                        'U_Shipping_Address' => $shippingAddress,
                        'U_Billing_Address' => $billingAddress,
                    ];
                
                }

                if($orders['data']['count'] == $orders['data']['countTotal']){
                    $moreOrders = false;
                }else{  
                    $offset += $orders['data']['count'];
                }

            }else{
                $moreOrders = false;
            }

        }

        if(!empty($orderIdArray)){
    
            $orderIds = '['.implode(',',$orderIdArray).']';
            $orderItems = $lazadaAPI->getMultipleOrderItems($orderIds);
            $counter = 0;
            $errorOrders = [];

            foreach ($orderItems['data'] as $item) {
                $orderId = $item['order_id'];
                $subTotal = 0;
                
                foreach($item['order_items'] as $orderItem){
                    if($orderItem['status'] == 'returned'){

                        try {
                            $result = $odataClient->getOdataClient()->from('Items')
                                            ->select('ItemCode','ItemName')
                                            ->where('U_LAZ2_SELLER_SKU',$orderItem['sku'])
                                            ->first();
                        } catch (ClientException $e) {
                            $msg = "Item ".$orderItem['sku']." on order ".$orderId." has problem".
                            $lazadaLog->writeSapLog($e,$msg);
                        }
                        
                        if(isset($result)){
                            $items[$orderId][] = [
                                'ItemCode' => $result->ItemCode,
                                'Quantity' => 1,
                                'VatGroup' => $taxCode->Name,
                                'UnitPrice' => $orderItem['item_price'] / $percentage->Name,
                                'WarehouseCode' => $whsCode->Name
                            ];

                            $subTotal += $orderItem['item_price'];
                        }
                        
                    }
                    
                }
                
                if(isset($items[$orderId])){
                    $tempCM[$orderId]['DocTotal'] = $subTotal;
                    $tempCM[$orderId]['DocumentLines'] = $items[$orderId];
                }
                
            }
            
            foreach($tempCM as $key => $value){
                $finalCM = array_slice($tempCM[$key],0);
                $getCM = $odataClient->getOdataClient()->from('CreditNotes')
                                ->where('U_Order_ID',(string)$finalCM['U_Order_ID'])
                                ->where('U_Ecommerce_Type','Lazada_2')
                                ->where(function($query){
                                    $query->where('DocumentStatus','bost_Open');
                                    $query->orWhere('DocumentStatus','bost_Close');
                                })
                                ->where('Cancelled','tNO')
                                ->first();

                if(!$getCM){

                    try {
                        $odataClient->getOdataClient()->post('CreditNotes',$finalCM);
                    
                        $counter++;
                    
                        Log::channel('lazada2.credit_memo')->info('Credit memo for Lazada order:'.$finalCM['U_Order_ID'].' created successfully.');

                    } catch (ClientException $e) {
                        $msg = "Order ".$finalCM['U_Order_ID']." has problems";
                
                        $lazadaLog->writeSapLog($e,$msg);

                        array_push($errorOrders,$finalCM['U_Order_ID']);
                    }

                }else{
                    unset($finalCM);
                }
                
            }

            $errors = implode(", ",$errorOrders);

            $success = array(
                'success_title' => 'Success: ',
                'success_message' => '<b>'.$counter.'</b> New A/R Credit Memos Generated.',
            );

            $danger = array(
                'danger_title' => 'Error: ',
                'danger_message' => 'Problems encountered while generating <b>'.count($errorOrders).'</b> order/s: '.$errors.'.'
            );

            if($counter > 0 && count($errorOrders) > 0){

                return response()->json(array_merge($success,$danger));

            }else if($counter > 0 && count($errorOrders) == 0){

                return response()->json($success);

            }else if($counter == 0 && count($errorOrders) > 0){

                return response()->json($danger);
            
            }else{

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'Returned orders were already generated.'
                ]);
            }

        }else{

            return response()->json([
                'title' => 'Information: ',
                'status' => 'alert-info',
                'message' => 'No returned orders for now.'
            ]);
        }
        
    }

}
