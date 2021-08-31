<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;

class LazadaItemMasterTemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:item-master-dump';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $odataClient = new SapService();
        
        $count = 0;
        
        $moreItems = true;
        
        while($moreItems){
            
            $getItems = $odataClient->getOdataClient()->from('Items')->where('U_LAZ_INTEGRATION','Yes')->skip($count)->get();//Live - Y

            if($getItems->isNotEmpty()){
                
                foreach($getItems as $item){
                    print_r("Item Code - ".$item['ItemCode']."\n");
                }

                $count += count($getItems);

            }else{
                $moreItems = false;
            }

        }

        if($moreItems == false){
            print_r('Success!!');
        }
    }
}
