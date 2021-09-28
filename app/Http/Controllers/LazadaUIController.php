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
            //LIVE - U_MPS_ECOMMERCE
            $brand = $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_BRAND')->first();
            $primaryCategory = $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_CATEGORY')->first();
            $deliveryOption = $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_DELIVERY_OPTION')->first();
            $packageHeight = $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_PACKAGE_HEIGHT')->first();
            $packageLength = $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_PACKAGE_LENGTH')->first();
            $packageWeight = $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_PACKAGE_WEIGHT')->first();
            $packageWidth = $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_PACKAGE_WIDTH')->first();
            $warrantyType= $odataClient->getOdataClient()->from('U_L1DD')->where('Code','L1_DFLT_WARRANTY_TYPE')->first();
            
            $count = 0;

            $itemCount = 0;

            $moreItems = true;

            while($moreItems){
                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ_INTEGRATION','Yes')->skip($count)->get();

                if($getItems->isNotEmpty()){

                    $lazadaAPI = new LazadaAPIController();

                    foreach($getItems as $item){

                        $fields = [
                            'itemName' => $item['ItemName'],
                            'sellerSku' => $item['ItemCode'],
                            'quantity' => $item['QuantityOnStock'],
                            'price' => $item['ItemPrices']['8']['Price'],
                        ];

                        $createProductPayload = "
                            <Request>
                                <Product>
                                    <PrimaryCategory>".$primaryCategory->U_VALUE."</PrimaryCategory>
                                    <Attributes>
                                        <name>".$fields['itemName']."</name>
                                        <brand>".$brand->U_VALUE."</brand>
                                        <delivery_option_sof>".$deliveryOption->U_VALUE."</delivery_option_sof>
                                        <warranty_type>".$warrantyType->U_VALUE."</warranty_type>
                                    </Attributes>
                                    <Skus>
                                        <Sku>
                                            <SellerSku>".$fields['sellerSku']."</SellerSku>
                                            <quantity>".$fields['quantity']."</quantity>
                                            <price>".$fields['price']."</price>
                                            <package_length>".$packageLength->U_VALUE."</package_length>
                                            <package_height>".$packageHeight->U_VALUE."</package_height>
                                            <package_weight>".$packageWeight->U_VALUE."</package_weight>
                                            <package_width>".$packageWidth->U_VALUE."</package_width>
                                        </Sku>
                                    </Skus>
                                </Product>
                            </Request>";
                        
                        $response = $lazadaAPI->createProduct($createProductPayload);

                        if(!empty($response['data'])){
                            $itemId = $response['data']['item_id'];
                            $update = $odataClient->getOdataClient()->from('Items')
                                        ->whereKey($fields['sellerSku'])
                                        ->patch([
                                            'U_LAZ_ITEM_CODE' => $itemId,
                                        ]);
                            
                            ($update ? $itemCount++ : '');
                        }
                    
                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
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
                                ->skip($count)
                                ->get();

                if($getItems->isNotEmpty()){

                    foreach($getItems as $item){
                        
                        $items[] = [
                            'newSku' => $item['ItemCode'],
                            'oldSku' => $item['U_MPS_OLDSKU']
                        ];
                        
                    }

                    $count += count($getItems);

                }else{
                    $moreItems = false;
                }
            }

            $lazadaAPI = new LazadaAPIController();
            $batch = array_chunk($items,50);

            foreach($batch as $b){

                foreach($b as $key){
                    
                    $newSku = $key['newSku'];
                    $oldSku = $key['oldSku'];
                    $getByNewSku = $lazadaAPI->getProductItem($newSku);

                    if(!empty($getByNewSku['data'])){
                        $lazadaItemId = $getByNewSku['data']['item_id'];

                        $update = $odataClient->getOdataClient()->from('Items')
                                ->whereKey($newSku)
                                ->patch([
                                    'U_LAZ_ITEM_CODE' => $lazadaItemId,
                                ]);
                        
                        ($update ? $itemCount ++ : '');

                    }else if($oldSku != null){
                        $getByOldSku = $lazadaAPI->getProductItem($oldSku);
                        
                        if(!empty($getByOldSku['data'])){
                            $lazadaItemId = $getByOldSku['data']['item_id'];
                            $oldSkuItemCode = $odataClient->getOdataClient()->from('Items')
                                                    ->where('U_MPS_OLDSKU',$oldSku)
                                                    ->first();

                            $update = $odataClient->getOdataClient()->from('Items')
                                    ->whereKey($oldSkuItemCode->ItemCode)
                                    ->patch([
                                        'U_LAZ_ITEM_CODE' => $lazadaItemId,
                                    ]);
                            
                            ($update ? $itemCount ++ : '');
                        }
                    }

                }
            }

            if($itemCount > 0){
                Log::channel('lazada.item_master')->info($itemCount.' Item Id UDFs updated.');

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => $itemCount.' Item Id UDFs updated.'
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

                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ_INTEGRATION','Yes')->skip($count)->get();//Live - Y/N

                if($getItems->isNotEmpty()){
                    
                    foreach($getItems as $item){

                        $items[] = [
                            'oldSku' => $item['U_OLD_SKU'],//Live - U_MPS_OLDSKU
                            'newSku' => $item['ItemCode'],
                            'price' => $item['ItemPrices']['8']['Price'], //live - $item['ItemPrices']['3']['Price']
                        ];

                    }

                    $count += count($getItems);
                
                }else{
                    $moreItems = false;
                }

            }

            $lazadaAPI = new LazadaAPIController();
            
            $batch = array_chunk($items,50);
            
            $skuPayload = [];
            
            $skuPayloadCount = 0;

            foreach($batch as $b){

                foreach($b as $key){
                        
                    $newSku = $key['newSku'];
                    $oldSku = $key['oldSku'];
                    $price = $key['price'];
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

                    if(count($skuPayload) >= 20){
                        unset($skuPayload);
                    }

                    if(!empty($lazadaItemId) && !empty($finalSku)){
                        //Create SKU Payload
                        $skuPayload[] = "<Sku>
                                            <ItemId>".$lazadaItemId."</ItemId>
                                            <SellerSku>".$finalSku."</SellerSku>
                                            <Price>".$price."</Price>
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
                $updatePrice = $lazadaAPI->updatePriceQuantity($finalPayload);

                if($updatePrice['code'] == 0){
                    $skuPayloadCount += count($skuPayload);
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
                
                $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ_INTEGRATION','Yes')->skip($count)->get();//Live - Y/N

                if($getItems->isNotEmpty()){

                    foreach($getItems as $item){

                        $items[] = [
                            'oldSku' => $item['U_OLD_SKU'],//Live - U_MPS_OLDSKU
                            'newSku' => $item['ItemCode'],
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
            
            $batch = array_chunk($items,50);
            
            $skuPayload = [];
            
            $skuPayloadCount = 0;

            foreach($batch as $b){

                foreach($b as $key){

                    if($key['invItem'] == 'tYES'){

                        $newSku = $key['newSku'];
                        $oldSku = $key['oldSku'];
                        $stock = $key['stock'];
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

                        if(count($skuPayload) > 20){
                            unset($skuPayload);
                        }
                        
                        if(!empty($lazadaItemId) && !empty($finalSku)){
                            //Create SKU Payload
                            $skuPayload[] = "<Sku>
                                                <ItemId>".$lazadaItemId."</ItemId>
                                                <SellerSku>".$finalSku."</SellerSku>
                                                <Quantity>".$stock."</Quantity>
                                            </Sku>";
                        }

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
            //LIVE - U_MPS_ECOMMERCE
            $lazadaCustomer = $odataClient->getOdataClient()->from('U_ECM')->where('Code','LAZADA1_CUSTOMER')->first();
            $sellerVoucher = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SELLER_VOUCHER')->first();
            $shippingFee = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SHIPPING_FEE')->first();
            $taxCode = $odataClient->getOdataClient()->from('U_ECM')->where('Code','TAX_CODE')->first();
            $percentage = $odataClient->getOdataClient()->from('U_ECM')->where('Code','PERCENTAGE')->first();
            $whsCode = $odataClient->getOdataClient()->from('U_ECM')->where('Code','WAREHOUSE_CODE')->first();

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
                            'DocTotal' => ($order['price'] + $order['shipping_fee']) - $order['voucher']
                        ];
                        
                        if($order['shipping_fee'] != 0.00){
                            $fees[$orderId][] = [
                                'ItemCode' => $shippingFee->Name,
                                'Quantity' => 1,
                                'VatGroup' => $taxCode->Name,
                                'UnitPrice' => $order['shipping_fee'] / $percentage->Name,
                                'WarehouseCode' => $whsCode->Name
                            ];
                        }
    
                        if($order['voucher'] != 0.00){
                            $fees[$orderId][] = [
                                'ItemCode' => $sellerVoucher->Name,
                                'Quantity' => -1,
                                'VatGroup' => $taxCode->Name,
                                'UnitPrice' => $order['voucher'] / $percentage->Name,
                                'WarehouseCode' => $whsCode->Name
                            ];
                        }
    
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
                        $items[$orderId][] = [
                            'ItemCode' => $orderItem['sku'],
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
                    $orderDocEntry = $odataClient->getOdataClient()->select('DocNum')->from('Orders')
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
                        $getSO = $odataClient->getOdataClient()->from('Orders')->find($orderDocEntry['DocNum']);
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
            $lazadaCustomer = $odataClient->getOdataClient()->from('U_ECM')->where('Code','LAZADA1_CUSTOMER')->first();
            $sellerVoucher = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SELLER_VOUCHER')->first();
            $shippingFee = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SHIPPING_FEE')->first();
            $taxCode = $odataClient->getOdataClient()->from('U_ECM')->where('Code','TAX_CODE')->first();
            $percentage = $odataClient->getOdataClient()->from('U_ECM')->where('Code','PERCENTAGE')->first();
            $whsCode = $odataClient->getOdataClient()->from('U_ECM')->where('Code','WAREHOUSE_CODE')->first();

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
                    
                    foreach($item['order_items'] as $orderItem){
                        if($orderItem['status'] == 'returned'){
                            $shippingAmount = $orderItem['shipping_amount'];
                            $paidPrice = $orderItem['paid_price'];
                            
                            if($shippingAmount != 0){
                                $finalPrice = $paidPrice + $shippingAmount;
                            }else{
                                $finalPrice = $paidPrice;
                            }
                            
                            $items[$orderId][] = [
                                'ItemCode' => $orderItem['sku'],
                                'Quantity' => 1,
                                'VatGroup' => $taxCode->Name,
                                'UnitPrice' => $finalPrice / $percentage->Name,
                                'WarehouseCode' => $whsCode->Name
                            ];

                            $refund[$orderId][] = $finalPrice;
                        }
                        
                    }
                    
                    $tempCM[$orderId]['DocTotal'] = array_sum($refund[$orderId]);
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
