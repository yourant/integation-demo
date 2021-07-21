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
        
            $lazadaCustomer = $odataClient->getOdataClient()->from('U_ECM')->where('Code','LAZADA_CUSTOMER')->first();
            $sellerVoucher = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SELLER_VOUCHER')->first();
            $shippingFee = $odataClient->getOdataClient()->from('U_ECM')->where('Code','SHIPPING_FEE')->first();
            
            $lazadaAPI = new LazadaAPIController();
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
                        'U_Ecommerce_Type' => 'Lazada',
                        'U_Order_ID' => $orderId,
                        'U_Customer_Name' => $order['customer_first_name'].' '.$order['customer_last_name'],
                        'DocTotal' => ($order['price'] + $order['shipping_fee']) - $order['voucher']
                    ];
        
                    if($order['shipping_fee'] != 0.00){
                        $fees[$orderId][] = [
                            'ItemCode' => $shippingFee->Name,
                            'Quantity' => 1,
                            'VatGroup' => 'SR',
                            'UnitPrice' => $order['shipping_fee'] / 1.07
                        ];
                    }

                    if($order['voucher'] != 0.00){
                        $fees[$orderId][] = [
                            'ItemCode' => $sellerVoucher->Name,
                            'Quantity' => -1,
                            'VatGroup' => 'SR',
                            'UnitPrice' => $order['voucher'] / 1.07
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
                            'VatGroup' => 'SR',
                            'UnitPrice' => $orderItem['item_price'] / 1.07
                        ];
                        
                    }
        
                    if(!empty($fees[$orderId])){
                        $tempCM[$orderId]['DocumentLines'] = array_merge($items[$orderId],$fees[$orderId]);
                    }else{
                        $tempCM[$orderId]['DocumentLines'] = $items[$orderId];
                    }
                    
                }
        
                foreach($tempCM as $key => $value){
                    $finalCM = array_slice($tempCM[$key],0);
                    $getCM = $odataClient->getOdataClient()->from('CreditNotes')
                                    ->where('U_Order_ID',(string)$finalCM['U_Order_ID'])
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
