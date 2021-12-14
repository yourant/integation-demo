<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Services\LazadaLogService;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;
use App\Http\Controllers\Lazada2APIController;

class Lazada2Order extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:sales-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada Order';

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
                        'U_Ecommerce_Type' => 'Lazada_1',
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
                        $salesOrder = $odataClient->getOdataClient()->post('Orders',$finalSO);

                        Log::channel('lazada2.sales_order')->info('Sales order for Lazada order:'.$finalSO['U_Order_ID'].' created successfully.');

                    } catch (ClientException $e) {
                        $msg = "Order ".$finalSO['U_Order_ID']." has problems";
                        $lazadaLog->writeSapLog($e,$msg);                            
                    }

                }else{
                    unset($finalSO);
                }

            }

        }else{
            Log::channel('lazada2.sales_order')->info('No pending orders for now.');
        }

        
    }
}
