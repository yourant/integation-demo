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
        $orders = $lazadaAPI->getPendingOrders();
        $orderArray = [];

        if(!empty($orders['data']['orders'])){
            foreach($orders['data']['orders'] as $order){
                $orderId = $order['order_id'];
                array_push($orderArray,$orderId);
            }

            foreach($orderArray as $id){
                $orderDocEntry = $odataClient->getOdataClient()->select('DocNum')->from('Orders')
                                            ->where('U_Order_ID',(string)$id)
                                            ->first();
                $getSO = $odataClient->getOdataClient()->from('Orders')->find($orderDocEntry['DocNum']);
                $getInv = $odataClient->getOdataClient()->from('Invoices')
                                    ->where('U_Order_ID',(string)$id)
                                    ->first();
                if($getSO && !$getInv){
                    $items = [];
                    foreach ($getSO['DocumentLines'] as $key => $value) {
                        $batchList = [];
                        if($value['BatchNumbers']) {                  
                            foreach ($value['BatchNumbers'] as $batch) {
                                $batchList[] = [
                                    'BatchNumber' => $batch['BatchNumber'],
                                    'Quantity' => $batch['Quantity']
                                ];
                            }
                        }
    
                        $items[] = [
                            'BaseType' => 17,
                            'BaseEntry' => $getSO['DocEntry'],
                            'BaseLine' => $key,
                            'BatchNumbers' => $batchList
                        ];
                    }
                    //Copy sales order to invoice
                    $odataClient->getOdataClient()->post('Invoices',[
                        'CardCode' => $getSO['CardCode'],
                        'DocDate' => $getSO['DocDate'],
                        'DocDueDate' => $getSO['DocDueDate'],
                        'PostingDate' => $getSO['TaxDate'],
                        'NumAtCard' => $getSO['NumAtCard'],
                        'U_Ecommerce_Type' => $getSO['U_Ecommerce_Type'],
                        'U_Order_ID' => $getSO['U_Order_ID'],
                        'U_Customer_Name' => $getSO['U_Customer_Name'].' '.$getSO['U_Customer_Email'],
                        'DocumentLines' => $items 
                    ]);
                    
                }else{
                    print_r("The sales order for ".$id." is already close\n");
                }
                
            }

        }else{
            print_r('No ready to ship orders for now!');
        }
        
        
    }
}
