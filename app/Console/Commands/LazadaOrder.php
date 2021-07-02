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
        $orders = $lazadaAPI->getPendingOrders();
        
        if(!empty($orders['data']['orders'])){
            foreach($orders['data']['orders'] as $order){
                $orderId = $order['order_id'];
                $orderIdArray[] = $order['order_id'];
    
                $tempSO[$orderId]['CardCode'] = 'Lazada_C';
                $tempSO[$orderId]['DocDate'] = '2021-07-02';
                $tempSO[$orderId]['DocDueDate'] = '2021-07-02';
                $tempSO[$orderId]['TaxDate'] = '2021-07-02';
                $tempSO[$orderId]['NumAtCard'] = $order['order_id'];
                $tempSO[$orderId]['U_Ecommerce_Type'] = 'Lazada';
                $tempSO[$orderId]['U_Order_ID'] = '23142';
                $tempSO[$orderId]['U_Customer_Name'] = $order['customer_first_name'].' '.$order['customer_last_name'];
    
            }
    
            $orderIds = '['.implode(',',$orderIdArray).']';
            $orderItems = $lazadaAPI->getMultipleOrderItems($orderIds);
            
            foreach ($orderItems['data'] as $item) {
                $orderId = $item['order_id'];
    
                foreach($item['order_items'] as $orderItem){
                    $items[$orderId][] = [
                        'ItemCode' => $orderItem['sku'],
                        'Quantity' => 1,
                        'TaxCode' => 'T1',
                        'UnitPrice' => $orderItem['item_price']
                    ];
                    
                }
    
                $tempSO[$orderId]['DocumentLines'] = $items[$orderId];
                
            }
    
            foreach($tempSO as $key => $value){
                $finalSO = array_slice($tempSO[$key],0);
                $odataClient->getOdataClient()->post('Orders',$finalSO);
            }

        }else{
            print_r('No orders for now!');
        }
        
    }
}
