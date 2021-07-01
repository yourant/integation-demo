<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;
use App\Http\Controllers\SAPLoginController;

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
        //SAP odataClient
        $odataClient = new SapService();
        //Get order
        $getOrder = $odataClient->getOdataClient()->from('Orders')
                                ->where('U_Order_ID','23142')
                                ->where('DocumentStatus','bost_Open') //Different SKU - Will use for demo
                                ->get();
        //Count items from Order
        for($i = 0; $i <= count($getOrder['0']['DocumentLines']) - 1; $i++) {
            $items[] = [
                'BaseType' => 17,
                'BaseEntry' => $getOrder['0']['DocEntry'],
                'BaseLine' => $i
            ];
        }
        //Insert invoice
        $odataClient->post('Invoices', [
            'CardCode' => $getOrder['0']['CardCode'],
            'DocDate' => $getOrder['0']['DocDate'],
            'DocDueDate' => $getOrder['0']['DocDueDate'],
            'PostingDate' => $getOrder['0']['TaxDate'],
            'NumAtCard' => $getOrder['0']['order_id'],
            'U_Ecommerce_Type' => 'Lazada',
            'U_Order_ID' => $getOrder['0']['U_Order_ID'],
            'U_Customer_Name' => $getOrder['0']['U_Customer_Name'].' '.$getOrder['0']['U_Customer_Email'],
            'DocumentLines' => $items 
            ]
        );
        
        
    }
}
