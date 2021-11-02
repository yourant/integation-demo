<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Services\LogService;
use App\Services\SapService;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;

class ShopeeUpdateIntegrationStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:status-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update integration status for the parsed csv';

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

        foreach ($shopeeItems as $item) {
            $validItem = null;
            $integrationStatus = null;
            $sku = $item[4] ? $item[4] : $item[5];

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
                    $logger->writeLog("Item with the Item Code {$validItem['properties']['ItemCode']} was updated with the integration status");
                }
            }
        }

        $logger->writeLog("Updated a total of {$successCount} items with its new integration status.");
        dd("Updated a total of {$successCount} items with its new integration status.");









        // $testArrNew = array_unique($testArr);
        // dd(count($testArr));
        // $finalTestArr = array_diff_assoc($testArr, $testArrNew);

        // $finalTestArr = array_diff($testArr, $testArrNew);
        // dd($finalTestArr);


        // try {
        //     $itemUpdateResponse2 = $itemSapService->getOdataClient()->from('Items')
        //         ->whereKey($itemUpdateResponse['properties']['ItemCode'])
        //         ->patch([
        //             'U_SH_INTEGRATION' => 'Y'
        //         ]);
        // } catch (ClientException $exception) {
        //     $logger->writeSapLog($exception);
        // }

        // if (isset($itemUpdateResponse2)) {
        //     $successCount2++;
        //     $logger->writeLog("{$countzzz}- Updated integration for item {$itemUpdateResponse['properties']['ItemCode']}");
        // }



        // $logger->writeLog("success count2 {$successCount2}");
        // dd($successCount2);
        // $logger->writeLog("success count {$successCount}");
        

        dd($successCount);

        

        


        dd($shopeeItems);





        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('item_create');
        
        $logger->writeLog('EXECUTING SHOPEE ITEM CREATE SCRIPT . . .');

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

        // retrieve detailed banned products
        $detailedBannedProductList = [];
        $moreBannedProducts = true;

        $logger->writeLog('Retrieving banned products . . .');
        
        while ($moreBannedProducts) {
            $bannedProductList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeToken->access_token);
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'BANNED',
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = $logger->validateResponse(json_decode($shopeeProductsResponse->body(), true));

            if ($shopeeProductsResponseArr) {
                if (array_key_exists('item', $shopeeProductsResponseArr['response'])) {
                    foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                        array_push($bannedProductList, $item['item_id']);
                    }
                }

                if ($bannedProductList) {
                    // get base info of product
                    $logger->writeLog('Retrieving base for banned products . . .');

                    $shopeeBaseProducts = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeToken->access_token);
                    $shopeeBaseProductsResponse = Http::get($shopeeBaseProducts->getFullPath(), array_merge([
                        'item_id_list' => implode(",", $bannedProductList)
                    ], $shopeeBaseProducts->getShopCommonParameter()));

                    $shopeeBaseProductsResponseArr = $logger->validateResponse(json_decode($shopeeBaseProductsResponse->body(), true));

                    if ($shopeeBaseProductsResponseArr) {
                        foreach ($shopeeBaseProductsResponseArr['response']['item_list'] as $item) {
                            array_push($detailedBannedProductList, $item);
                        }
                    }
                };

                if ($shopeeProductsResponseArr['response']['has_next_page']) {
                    $offset += $pageSize;
                } else {
                    $moreBannedProducts = false;
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

        // combine product base from products with different status
        $productList = array_merge($detailedNormalProductList, $detailedBannedProductList, $detailedUnlistedProductList);

        $logger->writeLog("Retrieved a total of " . count($productList) . " products.");
        
        // get the list of SKUs from the product list
        $skuList = [];

        $logger->writeLog('Retrieving product models . . .');

        foreach ($productList as $product) {
            if ($product['has_model']) {
                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = $logger->validateResponse(json_decode($shopeeModelsResponse->body(), true));

                if ($shopeeModelsResponseArr) {
                    foreach ($shopeeModelsResponseArr['response']['model'] as $model) {
                        array_push($skuList, $model['model_sku']);
                    }
                }
            } else {
                array_push($skuList, $product['item_sku']);
            }
        }   
        
        $logger->writeLog("Retrieved a total of " . count($skuList) . " SKUs.");

        $itemSapService = new SapService();

        // retrieve and organize default values for shopee
        $logger->writeLog('Retrieving Shopee default values . . .');

        $shDefaults = [];

        try {
            $shDefaults = $itemSapService->getOdataClient()->from('U_SHDD')->get();
        } catch (ClientException $exception) {
            $logger->writeSapLog($exception);
        }

        if ($shDefaults) {
            foreach ($shDefaults as $shDefault) {
                if ($shDefault['properties']['Code'] == 'SH_DFLT_BRAND_ID') {
                    $brandId = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_BRAND_NAME') {
                    $brandName = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_CATEGORY') {
                    $categoryId = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_DESCRIPTION') {
                    $description = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_IMG_ID') {
                    $imageId = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_LOGISTIC_ENABLED') {
                    $logisticEnabled = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_LOGISTIC_ID') {
                    $logisticId = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_STATUS') {
                    $status = $shDefault['properties']['U_VALUE'];
                } elseif ($shDefault['properties']['Code'] == 'SH_DFLT_WEIGHT') {
                    $weight = $shDefault['properties']['U_VALUE'];
                } 
            }

            $successCount = 0;
            $count = 0;
            $pageSize = 20;
            $moreItems = true; 
            $sapItemArr = [];
            $sapItemNameArr = [];
            
            // retrieve SAP B1 items and remove item duplicates based on the description
            $logger->writeLog('Retrieving SAP B1 items . . .');

            while ($moreItems) {
                $sapItems = [];

                try {
                    $sapItems = $itemSapService->getOdataClient()
                        ->select('ItemCode', 'ItemName', 'QuantityOnStock', 'ItemPrices', 'U_OLD_SKU')
                        ->from('Items')
                        ->where('U_SH_INTEGRATION','Yes')
                        ->whereNested(function($query) {
                            $query->where('U_SH_ITEM_CODE', NULL)
                                ->orWhere('U_SH_ITEM_CODE', '');
                        })->skip($count)
                        ->get();
                } catch (ClientException $exception) {
                    $logger->writeSapLog($exception);
                }

                if ($sapItems) {
                    foreach ($sapItems as $item) {
                        $itemName = $item['properties']['ItemName']; 
    
                        if (!in_array($itemName, $sapItemNameArr)) {
                            array_push($sapItemNameArr, $itemName); 
                            array_push($sapItemArr, $item); 
                        }           
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

            $logger->writeLog("Retrieved a total of " . count($sapItemArr) . " valid SAP B1 items.");
            
            // create new Shopee product
            $logger->writeLog('Creating new Shopee product(s) . . .');
    
            foreach ($sapItemArr as $sapItem) {
                $itemProp = $sapItem['properties'];
                $itemSku = $itemProp['U_OLD_SKU'] ?? $itemProp['ItemCode'];

                if (!in_array($itemSku, $skuList)) {
                    $shopeeAddProduct = new ShopeeService('/product/add_item', 'shop', $shopeeToken->access_token);
                    $shopeeAddProductResponse = Http::post($shopeeAddProduct->getFullPath() . $shopeeAddProduct->getShopQueryString(), [
                        'item_name' => $itemProp['ItemName'],
                        'description' => $description,
                        'original_price' => (float) $itemProp['ItemPrices'][9]['Price'],
                        'normal_stock' => (float) $itemProp['QuantityOnStock'],
                        'weight' => (float) $weight,
                        'item_sku' => $itemSku,
                        'item_status' => $status,
                        'category_id' => (int) $categoryId,
                        'brand' => [
                            'brand_id' => (int) $brandId,
                            'original_brand_name' => $brandName
                        ],
                        'image' => [
                            'image_id_list' => [
                                $imageId
                            ]
                        ],
                        'logistic_info' => [
                            [
                                'logistic_id' => (int) $logisticId,
                                'enabled' => filter_var($logisticEnabled, FILTER_VALIDATE_BOOLEAN)
                            ]
                        ]
                    ]);

                    $shopeeAddProductResponseArr = $logger->validateResponse(json_decode($shopeeAddProductResponse->body(), true));

                    if ($shopeeAddProductResponseArr) {
                        $successCount++;
                        $shProduct = $shopeeAddProductResponseArr['response'];
                        $logger->writeLog("Product {$shProduct['item_id']} was created with {$itemSku} SKU.");
                    }
                }
            }

            $logger->writeLog("Created a total of {$successCount} new Shopee product.");
        }
    }
}
