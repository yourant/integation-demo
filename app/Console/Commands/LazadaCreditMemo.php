<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaLoginController;

class LazadaCreditMemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:memo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada A/R Credit Memo';

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
        //Get Invoice
        $getInvoice = $odataClient->from('Invoices')
                                ->where('U_Order_ID','55551144238895') // Different SKU - Will use for demo
                                ->get();

        foreach($getInvoice['0']['DocumentLines'] as $item){
            $items[] = [
                'ItemCode' => $item['ItemCode'],
                'Price' => $item['Price'],
                'Quantity' => 1,
                'TaxCode' => 'T1'
            ];
        }
        //Insert Memo
        $odataClient->post('CreditNotes', [
                'CardCode' => $getInvoice['0']['CardCode'],
                'DocDate' => $getInvoice['0']['DocDate'],
                'DocDueDate' => $getInvoice['0']['DocDueDate'],
                'PostingDate' => $getInvoice['0']['TaxDate'],
                'DocumentLines' => $items 
            ]
        );

    }
}
