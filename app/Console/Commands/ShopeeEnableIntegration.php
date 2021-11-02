<?php

namespace App\Console\Commands;

use App\Services\LogService;
use App\Services\SapService;
use Illuminate\Console\Command;
use GuzzleHttp\Exception\ClientException;

class ShopeeEnableIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:integration-enable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable integration status for the parsed csv file of item list';

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
        $logger = new LogService('general');
        $itemSapService = new SapService();
        
        $logger->writeLog('Parsing CSV file of Shopee item list . . .');

        $csvFileName = "shopee_items.csv";
        $csvFile = storage_path('csv/' . $csvFileName);

        $file_handle = fopen($csvFile, 'r');
        while (!feof($file_handle)) {
            $shopeeItems[] = fgetcsv($file_handle, 0, ',');
        }

        fclose($file_handle);

        $successCount = 0;

        $logger->writeLog('Updating item integration status . . .');

        foreach ($shopeeItems as $key => $item) {
            $validItem = null;
            $integrationStatus = null;
            $sku = $item[4] ? $item[4] : $item[5];

            $counterz = $key + 1;
            $parentSku = $item[4]; 
            $varSku = $item[5];


            try {
                $validItem = $itemSapService->getOdataClient()
                    ->select('ItemCode')
                    ->from('Items')
                    ->where('Valid', 'tYES')
                    ->where('U_SH_INTEGRATION', 'N')
                    ->whereNested(function($query) use ($sku) {
                        $query->where('ItemCode', $sku)
                            ->orWhere('U_MPS_OLDSKU', $sku);
                    })->first();
            } catch (ClientException $exception) {
                $logger->writeSapLog($exception);
            } 

            if (isset($validItem)) {
                try {
                    $integrationStatus = $itemSapService->getOdataClient()->from('Items')
                        ->whereKey($validItem['properties']['ItemCode'])
                        ->patch([
                            'U_SH_INTEGRATION' => 'Y'
                        ]);
                } catch (ClientException $exception) {
                    $logger->writeSapLog($exception);
                }

                if (isset($integrationStatus)) {
                    $successCount++;
                    // $logger->writeLog("{$counterz} - successs");
                    // $logger->writeLog("Item with the Item Code {$validItem['properties']['ItemCode']} was updated with the integration status");
                }
            } else {
                $logger->writeLog("{$counterz} - parent: {$parentSku}");
                $logger->writeLog("{$counterz} - variant: {$varSku}");
            }
        }

        $logger->writeLog("Updated a total of {$successCount} items with its new integration status.");
    }
}