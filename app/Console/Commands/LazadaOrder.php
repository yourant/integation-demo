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
                $orderIdArray[] = $orderId;
    
                $tempSO[$orderId]['CardCode'] = 'Lazada_C';
                $tempSO[$orderId]['DocDate'] = substr($order['created_at'],0,10);
                $tempSO[$orderId]['DocDueDate'] = substr($order['created_at'],0,10);
                $tempSO[$orderId]['TaxDate'] = substr($order['created_at'],0,10);
                $tempSO[$orderId]['NumAtCard'] = $orderId;
                $tempSO[$orderId]['U_Ecommerce_Type'] = 'Lazada';
                $tempSO[$orderId]['U_Order_ID'] = $orderId;
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
