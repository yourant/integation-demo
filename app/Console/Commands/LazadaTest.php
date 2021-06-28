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
        $sku = '12345678a';
        //lazada
        $lazada = new LazadaController();
        $getProduct = $lazada->getProductItem($sku);
        $itemId = $getProduct['data']['item_id'];

        try {
            $odataClient->patch("Items("."'".$sku."'".")", [ //Items('12345678a')
                'U_LAZ_ITEM_CODE' => $itemId,
            ]);
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
        

    }

        
}

