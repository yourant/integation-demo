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

                return response()->json([
                    'title' => 'Success: ',
                    'status' => 'alert-success',
                    'message' => 'New tokens generated.'
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
            $counter = 0;

            if(!empty($getItems['0'])){
                //Loop results
                foreach($getItems as $item){
                    //Old and New SKU
                    $oldSku = $item['U_OLD_SKU']; //Old sku from SAP
                    $newSku = $item['ItemCode']; //Sku in lazada
                    $getByNewSku = $lazadaAPI->getProductItem($newSku);
                    
                    if(!empty($getByNewSku['data'])){
                        $lazadaItemId = $getByNewSku['data']['item_id'];
                        
                        $update = $odataClient->getOdataClient()->from('Items')
                                ->whereKey($newSku)
                                ->patch([
                                    'U_LAZ2_ITEM_CODE' => $lazadaItemId,
                                ]);
                        
                        ($update ? $counter++ : '');
                        
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
                            
                            ($update ? $counter++ : '');
                        }
                    }

                }

                if($counter > 0){
                    Log::channel('lazada2.update_price_qty')->info('Item Id UDFs updated.');

                    return response()->json([
                        'title' => 'Success: ',
                        'status' => 'alert-success',
                        'message' => 'Item Id UDFs updated.'
                    ]);
                }
            
            }else{
                Log::channel('lazada2.update_price_qty')->warning('No Lazada items available.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No Lazada items available.'
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('lazada2.update_price_qty')->emergency($e->getMessage());

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

                    return response()->json([
                        'title' => 'Success: ',
                        'status' => 'alert-success',
                        'message' => 'Items price updated.'
                    ]);

                }
            }else{
                Log::channel('lazada2.update_price_qty')->info('No Items price to be updated.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No Items price to be updated.'
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('lazada2.update_price_qty')->emergency($e->getMessage());

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

                    return response()->json([
                        'title' => 'Success: ',
                        'status' => 'alert-success',
                        'message' => 'Items stock updated.'
                    ]);

                }
            }else{
                Log::channel('lazada2.update_price_qty')->info('No Items stock to be updated.');

                return response()->json([
                    'title' => 'Information: ',
                    'status' => 'alert-info',
                    'message' => 'No Items stock to be updated.'
                ]);
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.update_price_qty')->emergency($e->getMessage());

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

            $lazadaCustomer = $odataClient->getOdataClient()->from('U_ECM')->where('Code','LAZADA2_CUSTOMER')->first();
            $sellerVoucher = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SELLER_VOUCHER')->first();
            $shippingFee = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SHIPPING_FEE')->first();
            $taxCode = $odataClient->getOdataClient()->from('U_ECM')->where('Code','TAX_CODE')->first();
            $percentage = $odataClient->getOdataClient()->from('U_ECM')->where('Code','PERCENTAGE')->first();

            $lazadaAPI = new Lazada2APIController();
            $orders = $lazadaAPI->getPendingOrders();

            if(!empty($orders['data']['orders'])){
                foreach($orders['data']['orders'] as $order){
                    $orderId = $order['order_id'];
                    $orderIdArray[] = $orderId;
                    
                    $tempSO[$orderId] = [
                        'CardCode' => $lazadaCustomer->Name,
                        'DocDate' => substr($order['created_at'],0,10),
                        'DocDueDate' => substr($order['created_at'],0,10),
                        'TaxDate' => substr($order['created_at'],0,10),
                        'NumAtCard' => $orderId,
                        'U_Ecommerce_Type' => 'Lazada_2',
                        'U_Order_ID' => $orderId,
                        'U_Customer_Name' => $order['customer_first_name'].' '.$order['customer_last_name'],
                        'DocTotal' => ($order['price'] + $order['shipping_fee']) - $order['voucher']
                    ];
                    
                    if($order['shipping_fee'] != 0.00){
                        $fees[$orderId][] = [
                            'ItemCode' => $shippingFee->Name,
                            'Quantity' => 1,
                            'VatGroup' => $taxCode->Name,
                            'UnitPrice' => $order['shipping_fee'] / $percentage->Name
                        ];
                    }

                    if($order['voucher'] != 0.00){
                        $fees[$orderId][] = [
                            'ItemCode' => $sellerVoucher->Name,
                            'Quantity' => -1,
                            'VatGroup' => $taxCode->Name,
                            'UnitPrice' => $order['voucher'] / $percentage->Name
                        ];
                    }

                }
        
                $orderIds = '['.implode(',',$orderIdArray).']';
                $orderItems = $lazadaAPI->getMultipleOrderItems($orderIds);

                foreach ($orderItems['data'] as $item) {
                    $orderId = $item['order_id'];
        
                    foreach($item['order_items'] as $orderItem){
                        $items[$orderId][] = [
                            'ItemCode' => $orderItem['sku'],
                            'Quantity' => 1,
                            'VatGroup' => $taxCode->Name,
                            'UnitPrice' => $orderItem['item_price'] / $percentage->Name
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
                                    ->where('U_Ecommerce_Type','Lazada_2')
                                    ->where(function($query){
                                        $query->where('DocumentStatus','bost_Open');
                                        $query->orWhere('DocumentStatus','bost_Close');
                                    })
                                    ->where('Cancelled','tNO')
                                    ->first();
                    
                    if(!$getSO){
                        $odataClient->getOdataClient()->post('Orders',$finalSO);
                        
                        Log::channel('lazada2.sales_order')->info('Sales order for Lazada order:'.$finalSO['U_Order_ID'].' created successfully.');
                    }else{
                        unset($finalSO);
                    }

                }

            }else{
                Log::channel('lazada2.sales_order')->info('No pending orders for now.');
            }

        } catch (\Exception $e) {
            Log::channel('lazada2.sales_order')->emergency($e->getMessage());
        }
    }

    public function generateInvoice()
    {
        try {
            $odataClient = new SapService();
            $lazadaAPI = new Lazada2APIController();
            $orders = $lazadaAPI->getReadyToShipOrders();
            $orderArray = [];

            if(!empty($orders['data']['orders'])){
                foreach($orders['data']['orders'] as $order){
                    $orderId = $order['order_id'];
                    array_push($orderArray,$orderId);
                }

                foreach($orderArray as $id){
                    $orderDocEntry = $odataClient->getOdataClient()->select('DocNum')->from('Orders')
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
                        
                        Log::channel('lazada2.ar_invoice')->info('A/R invoice for Lazada order:'.$getSO['U_Order_ID'].' created successfully.');
                    }
                    
                }

            }else{
                Log::channel('lazada2.ar_invoice')->info('No ready to ship orders for now.');
            }
        } catch (\Exception $e) {
            Log::channel('lazada2.ar_invoice')->emergency($e->getMessage());
        }
    }

    public function generateCreditMemo()
    {
        try {
            $odataClient = new SapService();
        
            $lazadaCustomer = $odataClient->getOdataClient()->from('U_ECM')->where('Code','LAZADA2_CUSTOMER')->first();
            $sellerVoucher = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SELLER_VOUCHER')->first();
            $shippingFee = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SHIPPING_FEE')->first();
            $taxCode = $odataClient->getOdataClient()->from('U_ECM')->where('Code','TAX_CODE')->first();
            $percentage = $odataClient->getOdataClient()->from('U_ECM')->where('Code','PERCENTAGE')->first();
            
            $lazadaAPI = new Lazada2APIController();
            $orders = $lazadaAPI->getReturnedOrders();
            
            if(!empty($orders['data']['orders'])){
                foreach($orders['data']['orders'] as $order){
                    $orderId = $order['order_id'];
                    $orderIdArray[] = $orderId;
                    
                    $tempCM[$orderId] = [
                        'CardCode' => $lazadaCustomer->Name,
                        'DocDate' => substr($order['created_at'],0,10),
                        'DocDueDate' => substr($order['created_at'],0,10),
                        'TaxDate' => substr($order['created_at'],0,10),
                        'NumAtCard' => $orderId,
                        'U_Ecommerce_Type' => 'Lazada_2',
                        'U_Order_ID' => $orderId,
                        'U_Customer_Name' => $order['customer_first_name'].' '.$order['customer_last_name'],
                    ];
                
                }
        
                $orderIds = '['.implode(',',$orderIdArray).']';
                $orderItems = $lazadaAPI->getMultipleOrderItems($orderIds);

                foreach ($orderItems['data'] as $item) {
                    $orderId = $item['order_id'];
        
                    foreach($item['order_items'] as $orderItem){
                        if($orderItem['status'] == 'returned'){
                            $items[$orderId][] = [
                                'ItemCode' => $orderItem['sku'],
                                'Quantity' => 1,
                                'VatGroup' => $taxCode->Name,
                                'UnitPrice' => $orderItem['paid_price'] / $percentage->Name
                            ];
                            $refund[$orderId][] = $orderItem['paid_price'];
                        }
                        
                    }
                    
                    $tempCM[$orderId]['DocTotal'] = array_sum($refund[$orderId]);
                    $tempCM[$orderId]['DocumentLines'] = $items[$orderId];
                    
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
                        $odataClient->getOdataClient()->post('CreditNotes',$finalCM);
                        
                        Log::channel('lazada2.credit_memo')->info('Credit memo for Lazada order:'.$finalCM['U_Order_ID'].' created successfully.');
                    }else{
                        unset($finalCM);
                    }
                    
                }

            }else{
                Log::channel('lazada2.credit_memo')->info('No returned orders for now.');
            }
        } catch (\Exception $e) {
            Log::channel('lazada2.credit_memo')->emergency($e->getMessage());
        }
    }


}
