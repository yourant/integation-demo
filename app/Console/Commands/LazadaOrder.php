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
        //Lazada Order
        $lazada = new LazadaController();
        //Step 1: Get Order Details
        $order = $lazada->getOrder('54385627928249');
        //Step 2: Get Order Item - SKU
        $orderItem = $lazada->getOrderItem($order['data']['order_id']);
        //Step 3: Get Product with SKU parameter
        $productItem = $lazada->getProductItem($orderItem['data']['0']['sku']);

        try {
            $result = $odataClient->from('Items')->find(''.$productItem['data']['item_id'].'');
			//$result = $odataClient->from('Items')->find('1360');
            $order = $odataClient->post('Orders', [
                'CardCode' => 'Lazada_C',
                'DocDate' => '2021-06-15',
                'DocDueDate' => '2021-06-15',
                'DocumentLines' => [
                    [
                        'ItemCode' => $productItem['data']['item_id'],
                        'Quantity' => $order['data']['items_count'],
                        'UnitPrice' => $orderItem['data']['0']['item_price']
                    ]
                ]
            ]);
		} catch (\Exception $e) {
            if($e->getCode() == '404'){
                $insert = $odataClient->post('Items', [
                    'ItemCode' => $productItem['data']['item_id'],
                    'ItemName' => $productItem['data']['attributes']['name'],
                    'ItemType' => 'itItems'
                ]);
                dd($insert);
            }else{
                dd($e->getMessage());
            }

		}
        //Step 4: Check if item exist
        
        //echo 'Get Order - Order ID is: '.$order['data']['order_id'].' / ';
        //echo 'Get Order Item - SKU is: '.$sku['data']['0']['sku'].' / ';
        //echo 'Product Item - Item ID and Name is:'.$itemId['data']['item_id'].' * '.$itemId['data']['attributes']['name'];
    }
}
