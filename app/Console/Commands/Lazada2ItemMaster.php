<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\Lazada2APIController;

class Lazada2ItemMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:item-master';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Item Master for 2nd Lazada Account';

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
        try {
            //SAP odataClient
            $odataClient = new SapService();
            //Get items with lazada integration set as yes
            $getItems = $odataClient->getOdataClient()->from('Items')
                                                ->where('U_LAZ2_INTEGRATION','Yes')
                                                ->get();
            if(!empty($getItems['0'])){
                //lazada API
                $lazadaAPI = new Lazada2APIController();
                //Loop Results
                foreach($getItems as $item){
                     //Price and Stocks
                     $sapPrice = $item['ItemPrices']['8']['Price'];
                     $sapStock = round($item['ItemWarehouseInfoCollection']['0']['InStock']);
                     //Old and New SKU
                     $oldSku = $item['U_OLD_SKU']; //Old sku from SAP
                     $newSku = $item['ItemCode']; //Sku in lazada
                }
            }
        } catch (\Exception $e) {
            
        }
    }
}
