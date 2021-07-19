<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
        try {
            $odataClient = new SapService();

            $lazadaCustomer = $odataClient->getOdataClient()->from('U_ECM')->where('Code','LAZADA_CUSTOMER')->first();
            $sellerVoucher = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SELLER_VOUCHER')->first();
            $shippingFee = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SHIPPING_FEE')->first();

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
                        'U_Ecommerce_Type' => 'Lazada',
                        'U_Order_ID' => $orderId,
                        'U_Customer_Name' => $order['customer_first_name'].' '.$order['customer_last_name'],
                    ];
                    
                    if($order['shipping_fee'] != 0.00){
                        $fees[$orderId][] = [
                            'ItemCode' => $shippingFee->Name,
                            'Quantity' => 1,
                            'VatGroup' => 'ZR',
                            'UnitPrice' => $order['shipping_fee']
                        ];
                    }

                    if($order['voucher'] != 0.00){
                        $fees[$orderId][] = [
                            'ItemCode' => $sellerVoucher->Name,
                            'Quantity' => -1,
                            'VatGroup' => 'ZR',
                            'UnitPrice' => $order['voucher']
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
                            'VatGroup' => 'ZR',
                            'UnitPrice' => $orderItem['item_price']
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
                                    ->where('DocumentStatus','bost_Open')
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
}
