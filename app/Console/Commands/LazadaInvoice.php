<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaLoginController;

class LazadaInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:invoice';

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
        $odataClient = (new LazadaLoginController)->login();
        //Insert invoices
        $getOrder = $odataClient->from('Orders')
                                ->where('U_Order_ID','54630739757010')
                                ->get();
        
        $invoice = $odataClient->post('Invoices', [
            'CardCode' => $getOrder['0']['CardCode'],
            'DocDate' => $getOrder['0']['DocDate'],
            'DocDueDate' => $getOrder['0']['DocDueDate'],
            'PostingDate' => $getOrder['0']['TaxDate'],
            'DocumentLines' => [
                [
                    'BaseType' => 17,
                    'BaseEntry' => $getOrder['0']['DocNum'],
                    'BaseLine' => 0
                ]
            ]
        ]);
        
    }
}
