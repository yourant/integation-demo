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
        //SAP odataClient
        $odataClient = (new LazadaLoginController)->login();
        //Lazada Controller
        $lazada = new LazadaController();
        //Step 1: Get Order Details
        $order = $lazada->getOrder('55138310480643');
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
                'ItemCode' => $item['ItemCode'],
                'Quantity' => $item['Quantity'],
                'UnitPrice' => $item['UnitPrice']
            ];
        }

        print_r($items);


    }

        
}

