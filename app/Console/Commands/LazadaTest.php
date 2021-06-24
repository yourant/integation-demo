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
        
        //$orderIds = '['.implode(',',$orderIdArray).']';
        $orderItems = $lazada->getMultipleOrderItems('[55370180470305,54789621886245]');
        foreach ($orderItems['data'] as $item) {
            $orderId = $item['order_id'];
            $mergedItem[$orderId] = [];

            foreach($item['order_items'] as $orderItem){
                $sku = $orderItem['sku'];
                $itemPrice = $orderItem['item_price'];
                if(array_key_exists($sku, $mergedItem[$orderId])) {
                    $mergedItem[$orderId][$sku]['Quantity'] += 1;
                    $mergedItem[$orderId][$sku]['UnitPrice'] += $itemPrice;
                } else {
                    $mergedItem[$orderId][$sku]['Quantity'] = 1;
                    $mergedItem[$orderId][$sku]['ItemCode'] = $sku;
                    $mergedItem[$orderId][$sku]['UnitPrice'] = $itemPrice;
                }
            }

            foreach ($mergedItem[$orderId] as $item) {
                $items[$orderId][] = [
                    'ItemCode' => $item['ItemCode'],
                    'Quantity' => $item['Quantity'],
                    "TaxCode" => 'T1',
                    'UnitPrice' => $item['UnitPrice']
                ];
            }
            
        }

        print_r($items);

        
    }

        
}

