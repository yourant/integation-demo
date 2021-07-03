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
                                    ->get();
    
                /**if(!empty($getSO['0'])){
                    //Count items from Order
                    if($getSO['0']['DocumentStatus'] == 'bost_Open'){
                        for($i = 0; $i <= count($getSO['0']['DocumentLines']) - 1; $i++) {
                            $items[] = [
                                'BaseType' => 17,
                                'BaseEntry' => $getSO['0']['DocEntry'],
                                'BaseLine' => $i
                            ];
                        }
                        //Insert invoice
                        $odataClient->getOdataClient()->post('Invoices', [
                            'CardCode' => $getSO['0']['CardCode'],
                            'DocDate' => $getSO['0']['DocDate'],
                            'DocDueDate' => $getSO['0']['DocDueDate'],
                            'PostingDate' => $getSO['0']['TaxDate'],
                            'NumAtCard' => $getSO['0']['NumAtCard'],
                            'U_Ecommerce_Type' => $getSO['0']['U_Ecommerce_Type'],
                            'U_Order_ID' => $getSO['0']['U_Order_ID'],
                            'U_Customer_Name' => $getSO['0']['U_Customer_Name'].' '.$getSO['0']['U_Customer_Email'],
                            'DocumentLines' => $items 
                            ]
                        );
                    }
                }else{
                    print_r('The order '.$id.' is already closed or not found in server. Please check');
                } **/
                
                
            }

        }else{
            print_r('No ready to ship orders for now!');
        }
        
    }
}
