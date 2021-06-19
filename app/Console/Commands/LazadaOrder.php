<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\LazadaLoginController;

class LazadaOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:order';

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
        //SAP odataClient
        $odataClient = (new LazadaLoginController)->login();
        //Lazada Controller
        $lazada = new LazadaController();
        //Step 1: Get Order Details
        $order = $lazada->getOrder('54630739757010');
        //Step 2: Get Order Item - SKU
        $orderItems = $lazada->getOrderItem($order['data']['order_id']);
        //Step 3: Get all items from selected order
        $mergedItem = [];
        foreach ($orderItems['data'] as $item) {
            // echo array_count_values($item['sku']);
            // $existingItem
            if(array_key_exists($item['sku'], $mergedItem)){
                $mergedItem[$item['sku']]['Quantity'] += 1;
            } else {
                $mergedItem[$item['sku']]['Quantity'] = 1;
            }
            $mergedItem[$item['sku']]['ItemCode'] = $item['sku'];
            $mergedItem[$item['sku']]['UnitPrice'] = $item['item_price'];
        }

        foreach ($mergedItem as $item) {
            $items[] = [
                'ItemCode' => '100344540', //sample Item Code only
                'Quantity' => $item['Quantity'],
                "TaxCode" => 'T1',
                'UnitPrice' => $item['UnitPrice']
            ];
        }

        print_r($items);
        //Step 4: Create Sales Order
        try {
            //$result = $odataClient->from('Items')->find(''.$productItem['data']['item_id'].'');
            $salesOrder = $odataClient->post('Orders', [
                'CardCode' => 'c001',
                'DocDate' => '2021-06-20',
                'DocDueDate' => '2021-06-20',
                'U_Order_ID' => $order['data']['order_id'],
                'U_Customer_Name' => 'Kassandra - Sales Order',
                'DocumentLines' => $items
            ]);
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
