<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;

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
        $lazada = new LazadaController();
        //Step 1: Get Order Details
        $order = $lazada->getOrder('54385627928249');
        //Step 2: Get Order Item - SKU
        $sku = $lazada->getOrderItem($order['data']['order_id']);
        //Step 3: Get Product with SKU parameter
        $itemId = $lazada->getProductItem($sku['data']['0']['sku']);
        //Step 4: Check if item exist in DB. If exists, skip the inserting item process otherwise create new Item
        $itemExist = $lazada->itemExist($itemId['data']['item_id']);
        
        /**
        $response = $lazada->getOrders(); 
        $response = Http::post('http://example.com/users', [
            'form_params' => [
                'CardCode' => 'c001',
                'DocDate' => '2021-06-11',
                'DocDueDate' => '2021-06-13',
                'DocumentLines' => [
                    'ItemCode' => 'i001',
                    'Quantity' => '100',
                    'UnitPrice' => '30'
                ]
            ]
        ]); **/

        //echo 'Get Order - Order ID is: '.$order['data']['order_id'].' / ';
        //echo 'Get Order Item - SKU is: '.$sku['data']['0']['sku'].' / ';
        //echo 'Product Item - Item ID and Name is:'.$itemId['data']['item_id'].' * '.$itemId['data']['attributes']['name'];
    }
}
