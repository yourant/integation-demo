<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LazadaAPIController;

class LazadaCreditMemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:credit-memo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada A/R Credit Memo';

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
                            'U_Ecommerce_Type' => 'Lazada_1',
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
                        
                        Log::channel('lazada.credit_memo')->info('Credit memo for Lazada order:'.$finalCM['U_Order_ID'].' created successfully.');
                    }else{
                        unset($finalCM);
                    }
                    
                }

            }else{
                Log::channel('lazada.credit_memo')->info('No returned orders for now.');
            }
            
        } catch (\Exception $e) {
            Log::channel('lazada.credit_memo')->emergency($e->getMessage());
        }

    }
}
