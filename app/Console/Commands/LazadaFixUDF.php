<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\LazadaAPIController;
use App\Http\Controllers\Lazada2APIController;

class LazadaFixUDF extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:fix-udf';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will fix the integration udf values of Lazada Account 1 and 2 items in SAP B2';

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
        $choice = $this->ask("1. Fix items Not Existed on Lazada Account 1(TC) but Existed on Lazada Account 2(MSG)"."\n".
                            "2. Fix items Not Existed on Lazada Account 2(MSG) but Existed on Lazada Account 1(TC)");

        $odataClient = new SapService();
        
        if($choice == 1){
            print_r("1. Fix items Not Existed on Lazada Account 1(TC) but Existed on Lazada Account 2(MSG)"."\n");

            $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ_INTEGRATION','Y')->get();

            if($getItems->isNotEmpty()){
                    
                $lazadaAPI = new LazadaAPIController();
                $lazada2API = new Lazada2APIController();
                
                foreach($getItems as $item){
                    //Old and New SKU
                    $itemName = $item['ItemName'];
                    $oldSku = $item['U_MPS_OLDSKU']; //Live - U_MPS_OLDSKU
                    $newSku = $item['ItemCode']; //New SKU
                    $getByNewSku = $lazadaAPI->getProductItem($newSku);
                    $getByNewSku2 = $lazada2API->getProductItem($newSku);
                    
                    if(empty($getByNewSku['data']) && !empty($getByNewSku2['data'])){
                        $itemCount++;
                        print_r("Item Name: ".$itemName." : New SKU: ".$newSku." : "."Old SKU: ".$oldSku."\n");

                    }else if($oldSku != null){
                        $getByOldSku = $lazadaAPI->getProductItem($oldSku);
                        $getByOldSku2 = $lazada2API->getProductItem($oldSku);
                        
                        if(empty($getByOldSku['data']) && !empty($getByOldSku2['data'])){
                            $itemCount++;
                            print_r("Item Name: ".$itemName." : New SKU: ".$newSku." : "."Old SKU: ".$oldSku."\n");
                        }
                    }
                    
                }

            }else{
                $moreItems = false;
                print_r("Total: ".$itemCount."\n");
            }

        }



    }
}
