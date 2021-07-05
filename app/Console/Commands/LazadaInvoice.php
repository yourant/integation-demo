<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\SAPLoginController;
use App\Http\Controllers\LazadaAPIController;

class LazadaInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:ar-invoice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada Invoice';

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
        $odataClient = new SapService();
        $lazadaAPI = new LazadaAPIController();
        $orders = $lazadaAPI->getReadyToShipOrders();
        $orderArray = [];

        if(!empty($orders['data']['orders'])){
            foreach($orders['data']['orders'] as $order){
                $orderId = $order['order_id'];
                array_push($orderArray,$orderId);
            }

            foreach($orderArray as $id){
                $getSO = $odataClient->getOdataClient()->from('Orders')
                                    ->where('U_Order_ID',(string)$id)
                                    ->where('DocumentStatus','bost_Open')
                                    ->get();
    
                if(!empty($getSO['0'])){
                    foreach($getSO as $So){
                        //Loop items
                        for($i = 0; $i <= count($So['DocumentLines']) - 1; $i++) {
                            $items[] = [
                                'BaseType' => 17,
                                'BaseEntry' => $So['DocEntry'],
                                'BaseLine' => $i
                            ];
                        }
                        //Copy sales order to invoice
                        $odataClient->getOdataClient()->post('Invoices',[
                            'CardCode' => $So['CardCode'],
                            'DocDate' => $So['DocDate'],
                            'DocDueDate' => $So['DocDueDate'],
                            'PostingDate' => $So['TaxDate'],
                            'NumAtCard' => $So['NumAtCard'],
                            'U_Ecommerce_Type' => $So['U_Ecommerce_Type'],
                            'U_Order_ID' => $So['U_Order_ID'],
                            'U_Customer_Name' => $So['U_Customer_Name'].' '.$So['U_Customer_Email'],
                            'DocumentLines' => $items 
                        ]);
                        //Unset 'items' variable to avoid error "One of the base documents has already been closed"
                        unset($items);
                        
                    }
                }else{
                    print_r("The sales order for ".$id." is already close\n");
                }
                
            }

        }else{
            print_r('No ready to ship orders for now!');
        }
        
    }
}
