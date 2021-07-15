<?php

namespace App\Console\Commands;

use App\Services\SapService;
use Illuminate\Console\Command;

class CreditMemoShopeeCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee-create:ar-credit-memo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create AR Credit Memo in SAP B1';

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
        $cMemo = [
            'CardCode' => 'Shopee_C',
            'NumAtCard' => '2106221FDBDPG4',
            'DocDate' => '1624351992',
            'DocDueDate' => '1624524805',
            'TaxDate' => '1624351992',
            'U_Ecommerce_Type' => 'Shopee',
            'U_Order_ID' => '2106221FDBDPG4',
            'U_Customer_Name' => 'Paul Jao',
            'U_Customer_Phone' => '639457505051',
            'U_Customer_Shipping_Address' => 'Makiling Street, Bermuda, Pamplona Uno Las Pinas, Pamplona Uno, Las Pinas City, Metro Manila, Metro Manila, 1742',
            'DocumentLines' => [
                0 => [
                    'ItemCode' => 'SH00002',
                    'Quantity' => 2,
                    'VatGroup' => 'ZR',
                    'UnitPrice' => 1000
                ],
                1 => [
                    'ItemCode' => 'SH00001',
                    'Quantity' => 1,
                    'VatGroup' => 'ZR',
                    'UnitPrice' => 200
                ]
                // ,
                // 2 => [
                //     'ItemCode' => 'TransportCharges',
                //     'Quantity' => 1,
                //     'VatGroup' => 'ZR',
                //     'UnitPrice' => 375
                // ]
            ]
        ];

        $creditMemoSapService = new SapService();
        $creditMemo = $creditMemoSapService->getOdataClient()->post('CreditNotes', $cMemo);

        // if ($creditMemo) {
        //     dd('Successfully created credit memo');
        // } else {
        //     dd('Failed to create credit memo');
        // }
    }
}
