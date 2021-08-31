<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaAPIController;

class LazadaOrderTemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:order-temp';

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
        $lazadaAPI = new LazadaAPIController();
        
        $offset = 0;
        
        $moreOrders= true;
        
        while($moreOrders){
            
            $orders = $lazadaAPI->getPendingOrders($offset);

            if(!empty($orders['data']['orders'])){
                
                foreach($orders['data']['orders'] as $item){
                    print_r("Item ID - ".$item['order_id']."\n");
                }

                if($orders['data']['count'] == $orders['data']['countTotal']){
                    
                    $moreOrders = false;
                
                }else{  

                    $offset += $orders['data']['count'];
                }

            }else{                
                $moreOrders = false;
                
                print_r('All Orders fetch');
            }

        }
        
        
    }
}
