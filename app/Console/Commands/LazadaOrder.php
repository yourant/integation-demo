<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\LazadaAPIController;

class LazadaOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:sales-order';

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
        $lazadaAPI = new LazadaAPIController();
        $orders = $lazadaAPI->getReadyToShipOrders();
        
        if(!empty($orders['data']['orders'])){
            foreach($orders['data']['orders'] as $order){
                $orderId = $order['order_id'];
                $orderIdArray[] = $orderId;
                
                $tempSO[$orderId] = [
                    'CardCode' => 'Lazada_C',
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
                        'ItemCode' => 'TransportCharges',
                        'Quantity' => 1,
                        'TaxCode' => 'T1',
                        'UnitPrice' => $order['shipping_fee']
                    ];
                }

                if($order['voucher'] != 0.00){
                    $fees[$orderId][] = [
                        'ItemCode' => 'SellerVoucher',
                        'Quantity' => 1,
                        'TaxCode' => 'T1',
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
                        'ItemCode' => 'i001',//$orderItem['sku'],
                        'Quantity' => 1,
                        'TaxCode' => 'T1',
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
                                ->get();

                if(empty($getSO['0'])){
                    $odataClient->getOdataClient()->post('Orders',$finalSO);
                }else{
                    unset($finalSO);
                }

            }

        }else{
            print_r('No pending orders for now!');
        }
        
    }
}
