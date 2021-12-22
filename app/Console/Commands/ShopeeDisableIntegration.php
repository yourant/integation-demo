<?php

namespace App\Console\Commands;

use App\Services\LogService;
use App\Services\SapService;
use Illuminate\Console\Command;
use GuzzleHttp\Exception\ClientException;

class ShopeeDisableIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:integration-disable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable integration status of item list';

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

        $logger->writeLog('EXECUTING SHOPEE INTEGRATION DISABLE SCRIPT . . .');

        $count = 0;
        $moreItems = true; 
        $sapItemArr = [];
        
        $logger->writeLog('Retrieving SAP B1 items . . .');

        while ($moreItems) {
            $sapItems = [];

            try {
                $sapItems = $itemSapService->getOdataClient()
                    ->select('ItemCode')
                    ->from('Items')
                    ->where('U_SH_INTEGRATION','Y')
                    ->where('Valid', 'tYES')
                    ->skip($count)
                    ->order('ItemCode')
                    ->get();
            } catch (ClientException $exception) {
                $logger->writeSapLog($exception);
            }

            if (isset($sapItems)) {
                foreach ($sapItems as $item) {  
                    array_push($sapItemArr, $item['properties']['ItemCode']);      
                }

                if (count($sapItems) > 0) {
                    $count += count($sapItems);
                } else {
                    $moreItems = false;
                }
            } else {
                break;
            }
        }

        $retrievedItems = count($sapItemArr);
        
        $logger->writeLog("Retrieved a total of {$retrievedItems} items.");
        
        $successCount = 0;
        
        $logger->writeLog("Updating item integration status . . .");

        foreach ($sapItemArr as $item) {
            $itemUpdateResponse = null;
            
            try {  
                $itemUpdateResponse = $itemSapService->getOdataClient()
                    ->from('Items')
                    ->whereKey($item)
                    ->patch([
                        'U_SH_ITEM_CODE' => NULL,
                        'U_SH_INTEGRATION' => 'N'
                    ]);  
            } catch (ClientException $exception) {
                $logger->writeSapLog($exception);
            }

            if (isset($itemUpdateResponse)) {
                $successCount++;
                $logger->writeLog("Item with the Item Code {$item} was updated with the integration status");
            }
        }

        $logger->writeLog("Updated a total of {$successCount} items with its new integration status.");
    }
}
