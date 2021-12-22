<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Services\LogService;
use App\Services\SapService;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
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
        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('general'); 

        $logger->writeLog('EXECUTING SHOPEE INTEGRATION ENABLE SCRIPT . . .');

        $productList = [];
        $offset = 0;
        $pageSize = 50;

        // retrieve detailed normal products
        $detailedNormalProductList = [];
        $moreNormalProducts = true;
        
        $logger->writeLog('Retrieving normal products . . .');

        while ($moreNormalProducts) {
            $normalProductList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeToken->access_token);
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'NORMAL',
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = $logger->validateResponse(json_decode($shopeeProductsResponse->body(), true));

            if ($shopeeProductsResponseArr) {
                if (array_key_exists('item', $shopeeProductsResponseArr['response'])) {
                    foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                        array_push($normalProductList, $item['item_id']);
                    }
                }
    
                if ($normalProductList) {
                    // get base info of product
                    $logger->writeLog('Retrieving base for normal products . . .');

                    $shopeeBaseProducts = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeToken->access_token);
                    $shopeeBaseProductsResponse = Http::get($shopeeBaseProducts->getFullPath(), array_merge([
                        'item_id_list' => implode(",", $normalProductList)
                    ], $shopeeBaseProducts->getShopCommonParameter()));
    
                    $shopeeBaseProductsResponseArr = $logger->validateResponse(json_decode($shopeeBaseProductsResponse->body(), true));
    
                    if ($shopeeBaseProductsResponseArr) {
                        foreach ($shopeeBaseProductsResponseArr['response']['item_list'] as $item) {
                            array_push($detailedNormalProductList, $item);
                        }
                    }
                }
    
                if ($shopeeProductsResponseArr['response']['has_next_page']) {
                    $offset += $pageSize;
                } else {
                    $moreNormalProducts = false;
                }
            } else {
                break;
            }
        }

        // retrieve detailed unlisted products
        $detailedUnlistedProductList = [];
        $moreUnlistedProducts = true;    
        
        $logger->writeLog('Retrieving unlisted products . . .');
        
        while ($moreUnlistedProducts) {
            $unlistedProductList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeToken->access_token);
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'UNLIST',
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = $logger->validateResponse(json_decode($shopeeProductsResponse->body(), true));

            if ($shopeeProductsResponseArr) {
                if (array_key_exists('item', $shopeeProductsResponseArr['response'])) {
                    foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                        array_push($unlistedProductList, $item['item_id']);
                    }
                }

                if ($unlistedProductList) {
                    // get base info of product
                    $logger->writeLog('Retrieving base for unlisted products . . .');

                    $shopeeBaseProducts = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeToken->access_token);
                    $shopeeBaseProductsResponse = Http::get($shopeeBaseProducts->getFullPath(), array_merge([
                        'item_id_list' => implode(",", $unlistedProductList)
                    ], $shopeeBaseProducts->getShopCommonParameter()));

                    $shopeeBaseProductsResponseArr = $logger->validateResponse(json_decode($shopeeBaseProductsResponse->body(), true));

                    if ($shopeeBaseProductsResponseArr) {
                        foreach ($shopeeBaseProductsResponseArr['response']['item_list'] as $item) {
                            array_push($detailedUnlistedProductList, $item);
                        }
                    }
                }

                if ($shopeeProductsResponseArr['response']['has_next_page']) {
                    $offset += $pageSize;
                } else {
                    $moreUnlistedProducts = false;
                } 
            } else {
                break;
            }  
        }

        // combine product base from products with normal and unlist status
        $productList = array_merge($detailedNormalProductList, $detailedUnlistedProductList);

        $logger->writeLog("Retrieved a total of " . count($productList) . " products.");

        $successCount = 0;
        
        $logger->writeLog("Updating Shopee Item Code UDF . . .");

        foreach ($productList as $prodCount => $product) {
            $itemSapService = new SapService();

            $parentSku = $product['item_sku'];

            $logger->writeLog($product['item_name']);

            // retrieve the model if it's applicable to the current product
            if ($product['has_model']) {
                $logger->writeLog('Retrieving product models . . .');

                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = $logger->validateResponse(json_decode($shopeeModelsResponse->body(), true));

                if ($shopeeModelsResponseArr) {
                    foreach ($shopeeModelsResponseArr['response']['model'] as $model) { 
                        if (isset($model['model_sku'])) {
                            $sku = $model['model_sku'];

                            try {
                                $validItem = $itemSapService->getOdataClient()
                                    ->select('ItemCode')
                                    ->from('Items')
                                    ->where('Valid', 'tYES')
                                    ->whereNested(function($query) {
                                        $query->where('U_SH_INTEGRATION', 'N')
                                            ->orWhere('U_SH_INTEGRATION', null);
                                    })->whereNested(function($query) use ($sku) {
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
                                    $logger->writeLog("{$prodCount} - successs");
                                }
                            } else {
                                $logger->writeLog("{$prodCount} - variant: {$sku}");
                            } 
                        }
                    }
                }  
            } else {
                try {
                    $item = $itemSapService->getOdataClient()
                        ->select('ItemCode')
                        ->from('Items')
                        ->whereNested(function($query) {
                            $query->where('U_SH_ITEM_CODE', NULL)
                                ->orWhere('U_SH_ITEM_CODE', '');
                        })->whereNested(function($query) use ($parentSku) {
                            $query->where('ItemCode', $parentSku)
                                ->orWhere('U_MPS_OLDSKU', $parentSku);
                        })->where('U_SH_INTEGRATION', 'Y')
                        ->first();

                    $validItem = $itemSapService->getOdataClient()
                        ->select('ItemCode')
                        ->from('Items')
                        ->where('Valid', 'tYES')
                        ->whereNested(function($query) {
                            $query->where('U_SH_INTEGRATION', 'N')
                                ->orWhere('U_SH_INTEGRATION', null);
                        })->whereNested(function($query) use ($parentSku) {
                            $query->where('ItemCode', $parentSku)
                                ->orWhere('U_MPS_OLDSKU', $parentSku);
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
                        $logger->writeLog("{$prodCount} - successs");
                    }
                } else {
                    $logger->writeLog("{$prodCount} - parent: {$parentSku}");
                }
            }
        }

        $logger->writeLog("Synced a total of {$successCount} Shopee SKUs.");
    }
}