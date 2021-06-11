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
        ]);

        echo 'order id is '.$response['data']['orders']['0']['order_id'];
    }
}
