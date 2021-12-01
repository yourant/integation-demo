<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Carbon\Carbon;
use App\Models\AccessToken;
use App\Services\SapService;
use Illuminate\Http\Request;
use App\Services\LazadaService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LazadaAPIController;

class LazadaUIController extends Controller
{
    public function index()
    {
        return view('lazada.dashboard');
    }

    public function refreshToken()
    {
        try
        {
            $lazadaToken = AccessToken::where('platform','lazada')->first();
            //Lazada Service
            $lazService = new LazadaService();
            //Lazada SDK
            $lazClient = new LazopClient($lazService->getAppUrl(),$lazService->getAppKey(),$lazService->getAppSecret());
            $lazRequest = new LazopRequest('/auth/token/refresh');
            $lazRequest->addApiParam('refresh_token',$lazadaToken->refresh_token);
            $response = json_decode($lazClient->execute($lazRequest));
            //Update Token
            $updatedToken = AccessToken::where('platform', 'lazada')
                    ->update([
                        'refresh_token' => $response->refresh_token,
                        'access_token' => $response->access_token
                    ]);

            if($updatedToken) {
                Log::channel('lazada.refresh_token')->info('New tokens generated.');

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => 'New tokens generated.'
                ]);

            } else {
                Log::channel('lazada.refresh_token')->emergency('Problem while generating tokens.');

                return response()->json([
                    'title' => 'Error: ',
                    'status' => 'alert-danger',
                    'message' => 'Problem while generating tokens.'
                ]);
            }
            
        }

        catch(\Exception $e)
        {
            Log::channel('lazada.refresh_token')->emergency($e->getMessage());

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

            while($moreItems){
                $getItems = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_LAZ_INTEGRATION','Y')
                                                    ->where('U_LAZ_ITEM_CODE',null)
                                                    ->skip($count)
                                                    ->get();

                if($getItems->isNotEmpty()){

                    foreach($getItems as $item){

                        $items[] = [
                            'itemName' => $item['ItemName'],
                            'sellerSku' => $item['ItemCode'],
                            'quantity' => $item['QuantityOnStock'],
                            'price' => $item['ItemPrices']['3']['Price'],
                        ];
                    
                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }
            }

            $itemCount = 0;

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
                
                $lazadaAPI = new LazadaAPIController();
                
                $response = $lazadaAPI->createProduct($createProductPayload);
                
                if(!empty($response['data'])){
                    $itemId = $response['data']['item_id'];
                    $sellerSku = $response['data']['sku_list']['0']['seller_sku'];
                    $update = $odataClient->getOdataClient()->from('Items')
                                ->whereKey($item['sellerSku'])
                                ->patch([
                                    'U_LAZ_ITEM_CODE' => $itemId,
                                    'U_LAZ_SELLER_SKU' => $sellerSku
                                ]);
                    
                    ($update ? $itemCount++ : '');
                }

            }

            if($itemCount > 0){
                Log::channel('lazada.item_master')->info($itemCount.' new product/s added.');

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => $itemCount.' new product/s added.'
                ]);

            }else{
                Log::channel('lazada.item_master')->info('No new Lazada products to be added.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No new Lazada products to be added.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());

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
                                ->where('U_LAZ_INTEGRATION','Y')
                                ->where('U_LAZ_ITEM_CODE',null)
                                ->where('U_LAZ_SELLER_SKU',null)
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
                
                $lazadaAPI = new LazadaAPIController();
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
                                                                                'U_LAZ_ITEM_CODE' => $product['item_id'],
                                                                                'U_LAZ_SELLER_SKU' => $sku['SellerSku']
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
                                                                                'U_LAZ_ITEM_CODE' => $product['item_id'],
                                                                                'U_LAZ_SELLER_SKU' => $sku['SellerSku']
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
                Log::channel('lazada.item_master')->info($itemCount.' Item UDFs updated.');

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => $itemCount.' Item UDFs updated.'
                ]);

            }else{
                Log::channel('lazada.item_master')->warning('No new Lazada items to be sync.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No new Lazada items to be sync.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function updatePrice()
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
                            'price' => $item['ItemPrices']['6']['Price']
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
                    $price = $key['price'];

                    //Create SKU Payload
                    $skuPayload[] = "<Sku>
                                        <ItemId>".$productId."</ItemId>
                                        <SellerSku>".$sellerSku."</SellerSku>
                                        <Price>".$price."</Price>
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
    
                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => 'Price updated on '.$skuPayloadCount.' Lazada SKU/s.'
                ]);

            }else{
                Log::channel('lazada.item_master')->warning('No Lazada items available.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No Lazada items available.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }

    }

    public function updateStock()
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
                            'stock' => $item['QuantityOnStock'],
                            'invItem' => $item['InventoryItem']
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

                    if($key['invItem'] == 'tYES'){

                        $sellerSku = $key['sellerSku'];
                        $productId = $key['productId'];
                        $stock = $key['stock'];
                        
                        //Create SKU Payload
                        $skuPayload[] = "<Sku>
                                            <ItemId>".$productId."</ItemId>
                                            <SellerSku>".$sellerSku."</SellerSku>
                                            <Quantity>".$stock."</Quantity>
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
                    $updateStock = $lazadaAPI->updatePriceQuantity($finalPayload);
                    
                    if($updateStock['code'] == 0){
                        $skuPayloadCount += count($skuPayload);
                        unset($skuPayload);
                    }
        
                }

            }

            if($skuPayloadCount > 0){
                Log::channel('lazada.item_master')->info('Stock updated on '.$skuPayloadCount.' Lazada SKU/s.');

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => 'Stock updated on '.$skuPayloadCount.' Lazada SKU/s.'
                ]);

            }else{
                Log::channel('lazada.item_master')->warning('No Lazada items available.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No Lazada items available.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada.item_master')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function generateSalesOrder()
    {
        try {
            $odataClient = new SapService();

            $lazadaCustomer = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','LAZADA1_CUSTOMER')->first();
            $taxCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','TAX_CODE')->first();
            $percentage = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','PERCENTAGE')->first();
            $whsCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','WAREHOUSE_CODE')->first();

            $lazadaAPI = new LazadaAPIController();

            $moreOrders= true;
            
            $offset = 0;

            $orderIdArray = [];

            while($moreOrders){
                
                $orders = $lazadaAPI->getPendingOrders($offset);

                if(!empty($orders['data']['orders'])){
                
                    foreach($orders['data']['orders'] as $order){
                        $orderId = $order['order_id'];
                        array_push($orderIdArray,$orderId);
                        
                        $tempSO[$orderId] = [
                            'CardCode' => $lazadaCustomer->Name,
                            'DocDate' => substr($order['created_at'],0,10),
                            'DocDueDate' => substr($order['created_at'],0,10),
                            'TaxDate' => substr($order['created_at'],0,10),
                            'NumAtCard' => $orderId,
                            'U_Ecommerce_Type' => 'Lazada_1',
                            'U_Order_ID' => $orderId,
                            'U_Customer_Name' => $order['customer_first_name'].' '.$order['customer_last_name'],
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
                
                foreach ($orderItems['data'] as $item) {
                    $orderId = $item['order_id'];
        
                    foreach($item['order_items'] as $orderItem){
                        $result = $odataClient->getOdataClient()->from('Items')
                                                        ->select('ItemCode','ItemName')
                                                        ->where('U_LAZ_SELLER_SKU',$orderItem['sku'])
                                                        ->first();

                        $items[$orderId][] = [
                            'ItemCode' => $result->ItemCode,
                            'Quantity' => 1,
                            'VatGroup' => $taxCode->Name,
                            'UnitPrice' => $orderItem['item_price'] / $percentage->Name,
                            'WarehouseCode' => $whsCode->Name
                        ];
                        
                    }

                    if(!empty($fees[$orderId])){
                        $tempSO[$orderId]['DocumentLines'] = array_merge($items[$orderId],$fees[$orderId]);
                    }else{
                        $tempSO[$orderId]['DocumentLines'] = $items[$orderId];
                    }

                }
                
                foreach($tempSO as $key => $value){
                    $finalSO = array_slice($tempSO[$key],0);
                    $getSO = $odataClient->getOdataClient()->from('Orders')
                                    ->where('U_Order_ID',(string)$finalSO['U_Order_ID'])
                                    ->where('U_Ecommerce_Type','Lazada_1')
                                    ->where(function($query){
                                        $query->where('DocumentStatus','bost_Open');
                                        $query->orWhere('DocumentStatus','bost_Close');
                                    })
                                    ->where('Cancelled','tNO')
                                    ->first();

                    if(!$getSO){
                        $odataClient->getOdataClient()->post('Orders',$finalSO);
                        
                        $counter++;
                        
                        Log::channel('lazada.sales_order')->info('Sales order for Lazada order:'.$finalSO['U_Order_ID'].' created successfully.');
                    }else{
                        unset($finalSO);
                    }

                }
                
                if($counter > 0){

                    return response()->json([
                        'title' => 'Success: ',
                        'status' => 'alert-success',
                        'message' => $counter. ' New Sales Orders Generated.'
                    ]);

                }else{

                    return response()->json([
                        'title' => 'Information: ',
                        'status' => 'alert-info',
                        'message' => 'No pending orders for now.'
                    ]);

                }

            }else{
                Log::channel('lazada.sales_order')->info('No pending orders for now.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No pending orders for now.'
                ]);

            }
        } catch (\Exception $e) {
            Log::channel('lazada.sales_order')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);

        }
    }

    public function generateInvoice()
    {
        try {
            $odataClient = new SapService();
        
            $lazadaAPI = new LazadaAPIController();
            
            $offset = 0;
            
            $moreOrders= true;

            $orderArray = [];

            while($moreOrders){

                $orders = $lazadaAPI->getReadyToShipOrders($offset);

                if(!empty($orders['data']['orders'])){
                    foreach($orders['data']['orders'] as $order){
                        $orderId = $order['order_id'];
                        array_push($orderArray,$orderId);
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
                
                foreach($orderArray as $id){
                    $orderDocEntry = $odataClient->getOdataClient()->select('DocEntry')->from('Orders')
                                        ->where('U_Order_ID',(string)$id)
                                        ->where('U_Ecommerce_Type','Lazada_1')
                                        ->where('DocumentStatus','bost_Open')
                                        ->where('Cancelled','tNO')
                                        ->first();
                    $getInv = $odataClient->getOdataClient()->from('Invoices')
                                        ->where('U_Order_ID',(string)$id)
                                        ->where('U_Ecommerce_Type','Lazada_1')
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
                        //Copy sales order to invoice
                        $odataClient->getOdataClient()->post('Invoices',[
                            'CardCode' => $getSO['CardCode'],
                            'DocDate' => $getSO['DocDate'],
                            'DocDueDate' => $getSO['DocDueDate'],
                            'PostingDate' => $getSO['TaxDate'],
                            'NumAtCard' => $getSO['NumAtCard'],
                            'U_Ecommerce_Type' => $getSO['U_Ecommerce_Type'],
                            'U_Order_ID' => $getSO['U_Order_ID'],
                            'U_Customer_Name' => $getSO['U_Customer_Name'].' '.$getSO['U_Customer_Email'],
                            'DocumentLines' => $items 
                        ]);
                        
                        $counter++;
                        
                        Log::channel('lazada.ar_invoice')->info('A/R invoice for Lazada order:'.$getSO['U_Order_ID'].' created successfully.');

                    }
                    
                }

                if($counter > 0){

                    return response()->json([
                        'title' => 'Success: ',
                        'status' => 'alert-success',
                        'message' => $counter. ' New A/R Invoices Generated.'
                    ]);

                }else{
                    
                    return response()->json([
                        'title' => 'Information: ',
                        'status' => 'alert-info',
                        'message' => 'No ready to ship orders for now.'
                    ]);

                }

            }else{
                Log::channel('lazada.ar_invoice')->info('No ready to ship orders for now.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No ready to ship orders for now.'
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('lazada.ar_invoice')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function generateCreditMemo()
    {
        try {
            $odataClient = new SapService();
            //LIVE - U_MPS_ECOMMERCE
            $lazadaCustomer = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','LAZADA1_CUSTOMER')->first();
            $taxCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','TAX_CODE')->first();
            $percentage = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','PERCENTAGE')->first();
            $whsCode = $odataClient->getOdataClient()->from('U_MPS_ECOMMERCE')->where('Code','WAREHOUSE_CODE')->first();

            $lazadaAPI = new LazadaAPIController();
            
            $moreOrders= true;
            
            $offset = 0;

            $orderIdArray = [];
            
            while($moreOrders){

                $orders = $lazadaAPI->getReturnedOrders($offset);
            
                if(!empty($orders['data']['orders'])){

                    foreach($orders['data']['orders'] as $order){
                        $orderId = $order['order_id'];
                        array_push($orderIdArray,$orderId);
                        
                        $tempCM[$orderId] = [
                            'CardCode' => $lazadaCustomer->Name,
                            'DocDate' => substr($order['created_at'],0,10),
                            'DocDueDate' => substr($order['created_at'],0,10),
                            'TaxDate' => substr($order['created_at'],0,10),
                            'NumAtCard' => $orderId,
                            'U_Ecommerce_Type' => 'Lazada_1',
                            'U_Order_ID' => $orderId,
                            'U_Customer_Name' => $order['customer_first_name'].' '.$order['customer_last_name'],
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

                foreach ($orderItems['data'] as $item) {
                    $orderId = $item['order_id'];
                    $subTotal = 0;

                    foreach($item['order_items'] as $orderItem){
                        if($orderItem['status'] == 'returned'){
                            $result = $odataClient->getOdataClient()->from('Items')
                                                ->select('ItemCode','ItemName')
                                                ->where('U_LAZ_SELLER_SKU',$orderItem['sku'])
                                                ->first();
                            
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
                    
                    $tempCM[$orderId]['DocTotal'] = $subTotal;
                    $tempCM[$orderId]['DocumentLines'] = $items[$orderId];
                    
                }
                
                foreach($tempCM as $key => $value){
                    $finalCM = array_slice($tempCM[$key],0);
                    $getCM = $odataClient->getOdataClient()->from('CreditNotes')
                                    ->where('U_Order_ID',(string)$finalCM['U_Order_ID'])
                                    ->where('U_Ecommerce_Type','Lazada_1')
                                    ->where(function($query){
                                        $query->where('DocumentStatus','bost_Open');
                                        $query->orWhere('DocumentStatus','bost_Close');
                                    })
                                    ->where('Cancelled','tNO')
                                    ->first();
                    if(!$getCM){
                        $odataClient->getOdataClient()->post('CreditNotes',$finalCM);
                        
                        $counter++;
                        
                        Log::channel('lazada.credit_memo')->info('Credit memo for Lazada order:'.$finalCM['U_Order_ID'].' created successfully.');

                    }else{
                        unset($finalCM);
                    }
                    
                }

                if($counter > 0){

                    return response()->json([
                        'title' => 'Success: ',
                        'status' => 'alert-success',
                        'message' => $counter. ' New A/R Credit Memos Generated.'
                    ]);

                }else{

                    return response()->json([
                        'title' => 'Information: ',
                        'status' => 'alert-info',
                        'message' => 'No returned orders for now.'
                    ]);

                }

            }else{
                Log::channel('lazada.credit_memo')->info('No returned orders for now.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No returned orders for now.'
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('lazada.credit_memo')->emergency($e->getMessage());

            return response()->json([
                'title' => 'Error: ',
                'status' => 'alert-danger',
                'message' => $e->getMessage()
            ]);
        }
    }
}
