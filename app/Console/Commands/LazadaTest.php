<?php

namespace App\Console\Commands;

use LazopClient;
use LazopRequest;
use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\LazadaLoginController;

class LazadaTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is for Lazada testing endpoints.';

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
        $odataClient = (new LazadaLoginController)->login();
        $lazada = new LazadaController();
        $orders = $lazada->getOrders();

        foreach($orders['data']['orders'] as $order){
            $orderIdArray[] = $order['order_id'];
        }
        
        $orderIds = '['.implode(',',$orderIdArray).']';
        $orderItems = $lazada->getMultipleOrderItems($orderIds);
        $mergedItem = [];
        foreach ($orderItems['data'] as $item) {
            $orderId = $item['order_id'];
            $sku = $item['order_items']['0']['sku'];
            $itemPrice = $item['order_items']['0']['item_price'];
            // $existingItem
            if(array_key_exists($sku, $mergedItem)){
                $mergedItem[$sku]['Quantity'] += 1;
            } else {
                $mergedItem[$sku]['Quantity'] = 1;
            }

            $mergedItem[$sku]['OrderId'] = $orderId;
            $mergedItem[$sku]['ItemCode'] = $sku;
            $mergedItem[$sku]['UnitPrice'] = $itemPrice;
        
        }

        foreach ($mergedItems as $item) {
            $salesOrders[] = [
                'CardCode' => '754',
                'DocDate' => '2021-06-25',
                'DocDueDate' => '2021-06-25',
                'U_Order_ID' => $item['OrderId'],
                'U_Customer_Name' => 'Kassandra - Sales Order',
                'DocumentLines' => [
                    'ItemCode' => '100344540', //sample Item Code only - suppose to be $item['ItemCode']
                    'Quantity' => $item['Quantity'],
                    "TaxCode" => 'T1',
                    'UnitPrice' => $item['UnitPrice']
                ]
            ];
        }

        try {
            //$result = $odataClient->from('Items')->find(''.$productItem['data']['item_id'].'');
            $salesOrder = $odataClient->post('Orders',$salesOrders);
		} catch (\Exception $e) {
            /**if($e->getCode() == '404'){
                $insert = $odataClient->post('Items', [
                    'ItemCode' => $productItem['data']['item_id'],
                    'ItemName' => $productItem['data']['attributes']['name'],
                    'ItemType' => 'itItems'
                ]);
                dd($insert);
            }else{
                dd($e->getMessage());
            }**/
            dd($e->getMessage());
		}
        

    }

        
}

