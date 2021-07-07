<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SapService;
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
        $odataClient = new SapService();
        $lazadaAPI = new LazadaAPIController();
        $orders = $lazadaAPI->getReturnedOrders();
        
        if(!empty($orders['data']['orders'])){
            foreach($orders['data']['orders'] as $order){
                $orderId = $order['order_id'];
                $orderIdArray[] = $orderId;
    
                $tempCM[$orderId]['CardCode'] = 'Lazada_C';
                $tempCM[$orderId]['DocDate'] = substr($order['created_at'],0,10);
                $tempCM[$orderId]['DocDueDate'] = substr($order['created_at'],0,10);
                $tempCM[$orderId]['TaxDate'] = substr($order['created_at'],0,10);
                $tempCM[$orderId]['NumAtCard'] = $orderId;
                $tempCM[$orderId]['U_Ecommerce_Type'] = 'Lazada';
                $tempCM[$orderId]['U_Order_ID'] = $orderId;
                $tempCM[$orderId]['U_Customer_Name'] = $order['customer_first_name'].' '.$order['customer_last_name'];
    
                if($order['shipping_fee'] != 0.00){
                    $fees[$orderId][] = [
                        'ItemCode' => 'TransportCharges',
                        'Quantity' => 1,
                        'TaxCode' => 'ZR',
                        'UnitPrice' => $order['shipping_fee']
                    ];
                }

                if($order['voucher'] != 0.00){
                    $fees[$orderId][] = [
                        'ItemCode' => 'SellerVoucher',
                        'Quantity' => -1,
                        'TaxCode' => 'ZR',
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
                        'TaxCode' => 'ZR',
                        'UnitPrice' => $orderItem['item_price']
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
                                ->where('DocumentStatus','bost_Open')
                                ->get();

                if(empty($getCM['0'])){
                    $odataClient->getOdataClient()->post('CreditNotes',$finalCM);
                }else{
                    unset($finalCM);
                }
                
            }

        }else{
            print_r('No returned orders for now!');
        }

    }
}
