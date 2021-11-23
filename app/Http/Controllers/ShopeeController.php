<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Services\LogService;
use App\Services\SapService;
use Illuminate\Http\Request;
use App\Services\ShopeeService;
use App\Traits\ResponseUtilTrait;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;

class ShopeeController extends Controller
{
    use ResponseUtilTrait;

    public function index()
    {
        return view('shopee.dashboard');
    }

    public function shopAuth()
    {
        $shopee = new ShopeeService('/shop/auth_partner');
        $timestamp = $shopee->getTimestamp();
        $partnerId = $shopee->getPartnerId();
        $partnerKey = $shopee->getPartnerKey();
        $path = $shopee->getPath();
        $host = $shopee->getHost();
        $redirectUrl = $shopee->getRedirectUrl();
        $baseString = $partnerId . $path . $timestamp;
        $sign = hash_hmac("sha256", $baseString, $partnerKey);
        return redirect()->away($host . $path . '?partner_id=' . $partnerId . '&redirect=' . $redirectUrl . '&sign=' . $sign . '&timestamp=' . $timestamp);
    }

    public function initToken(Request $request)
    {
        $shopeeAccess = new ShopeeService('/auth/token/get', 'public');

        $accessResponse = Http::post($shopeeAccess->getFullPath() . $shopeeAccess->getAccessTokenQueryString(), [
            'code' => $request->code,
            'partner_id' => $shopeeAccess->getPartnerId(),
            'shop_id' => (int) $request->shop_id
        ]);

        $accessResponseArr = json_decode($accessResponse->body(), true);
        $refreshToken = $accessResponseArr['refresh_token'];

        $shopeeRefreshToken = new ShopeeService('/auth/access_token/get', 'public');

        $refreshTokenResponse = Http::post($shopeeRefreshToken->getFullPath() . $shopeeRefreshToken->getAccessTokenQueryString(), [
            'refresh_token' => $refreshToken,
            'partner_id' => $shopeeRefreshToken->getPartnerId(),
            'shop_id' => (int) $request->shop_id
        ]);

        $refreshTokenResponseArr = json_decode($refreshTokenResponse->body(), true);

        $updatedToken = AccessToken::where('platform', 'shopee')
            ->update([
                'refresh_token' => $refreshTokenResponseArr['refresh_token'],
                'access_token' => $refreshTokenResponseArr['access_token'],
                'code' => $request->code,
                'shop_id' => (int) $request->shop_id,
            ]);

            $resultArr = [];
        
        if ($updatedToken) {
            $resultArr['status'] = 'success';
            $resultArr['msg'] = 'Successfully authorize shop';
        } else {
            $resultArr['status'] = 'error';
            $resultArr['msg'] = 'Failed to authorized shop';
        }

        return redirect()->route('shopee.dashboard')
            ->with(['status' => $resultArr['status'], 'msg' => $resultArr['msg']]);
    }

    public function createProduct()
    {
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
        $logger->writeLog('Retrieving Shopee product default values . . .');

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

        return response()->json($this->getJsonResponse($successCount, $logger->getErrorCount(), 'products', 'created'));    
    }

    public function syncItem()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('item_sync');  

        $logger->writeLog('EXECUTING SHOPEE ITEM SYNC SCRIPT . . .');

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

        foreach ($productList as $product) {
            $itemSapService = new SapService();

            $parentSku = $product['item_sku'];
            $productId = $product['item_id'];            

            // retrieve the model if it's applicable to the current product
            if ($product['has_model']) {
                $logger->writeLog('Retrieving product models . . .');

                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = $logger->validateResponse(json_decode($shopeeModelsResponse->body(), true));

                if ($shopeeModelsResponseArr) {
                    foreach ($shopeeModelsResponseArr['response']['model'] as $key => $model) { 
                        $sku = $model['model_sku'];

                        try {
                            $item = $itemSapService->getOdataClient()
                                ->select('ItemCode')
                                ->from('Items')
                                ->whereNested(function($query) {
                                    $query->where('U_SH_ITEM_CODE', NULL)
                                        ->orWhere('U_SH_ITEM_CODE', '');
                                })->whereNested(function($query) use ($sku) {
                                    $query->where('ItemCode', $sku)
                                        ->orWhere('U_OLD_SKU', $sku);
                                })->where('U_SH_INTEGRATION', 'Yes')
                                ->first();
                        } catch (ClientException $exception) {
                            $logger->writeSapLog($exception);
                        }

                        if (isset($item)) {
                            try {
                                $itemUpdateResponse = $itemSapService->getOdataClient()->from('Items')
                                    ->whereKey($item->ItemCode)
                                    ->patch([
                                        'U_SH_ITEM_CODE' => $productId
                                    ]);    
                            } catch (ClientException $exception) {
                                $logger->writeSapLog($exception);
                            }

                            if (isset($itemUpdateResponse)) {
                                $successCount++;
                                $logger->writeLog("Product with {$sku} variant SKU was synced to the item master.");
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
                                ->orWhere('U_OLD_SKU', $parentSku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->first();
                } catch (ClientException $exception) {
                    $logger->writeSapLog($exception);
                }

                if (isset($item)) {
                    try {
                        $itemUpdateResponse = $itemSapService->getOdataClient()->from('Items')
                            ->whereKey($item->ItemCode)
                            ->patch([
                                'U_SH_ITEM_CODE' => $productId
                            ]);  
                    } catch (ClientException $exception) {
                        $logger->writeSapLog($exception);
                    }

                    if (isset($itemUpdateResponse)) {
                        $successCount++;
                        $logger->writeLog("Product with {$parentSku} parent SKU was synced to the item master.");
                    }
                }
            }
        }

        $logger->writeLog("Synced a total of {$successCount} Shopee SKUs.");

        return response()->json($this->getJsonResponse($successCount, $logger->getErrorCount(), 'Shopee SKUs', 'synced'));
    }

    public function updatePrice()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('price_update');  

        $logger->writeLog('EXECUTING SHOPEE UPDATE PRICE SCRIPT . . .');

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

        $logger->writeLog("Updating Shopee product price . . .");

        foreach ($productList as $product) {
            $itemSapService = new SapService();

            $parentSku = $product['item_sku'];
            $productId = $product['item_id'];            

            // retrieve the model if it's applicable to the current product
            if ($product['has_model']) {
                $logger->writeLog('Retrieving product models . . .');

                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = $logger->validateResponse(json_decode($shopeeModelsResponse->body(), true));

                if ($shopeeModelsResponseArr) {
                    foreach ($shopeeModelsResponseArr['response']['model'] as $key => $model) { 
                        $modelId = $model['model_id'];
                        $sku = $model['model_sku'];

                        try {
                            $item = $itemSapService->getOdataClient()
                            ->select('ItemCode', 'ItemPrices')
                            ->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                        } catch (ClientException $exception) {
                            $logger->writeSapLog($exception);
                        }

                        if (isset($item)) {
                            $shopeePriceUpdate = new ShopeeService('/product/update_price', 'shop', $shopeeToken->access_token);
                            $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
                                'item_id' => (int) $productId,
                                'price_list' => [
                                    [
                                        'model_id' => (int) $modelId,
                                        'original_price' => (float) $item['ItemPrices'][9]['Price']
                                    ]
                                ]
                            ]);

                            $shopeePriceUpdateResponseArr = $logger->validateResponse(json_decode($shopeePriceUpdateResponse->body(), true));

                            if ($shopeePriceUpdateResponseArr) {
                                $successCount++;
                                $logger->writeLog("The product's price with {$sku} variant SKU was updated.");
                            }
                        }  
                    }
                } 
            } else {
                try {
                    $item = $itemSapService->getOdataClient()
                        ->select('ItemCode', 'ItemPrices')
                        ->from('Items')
                        ->whereNested(function($query) use ($parentSku) {
                            $query->where('ItemCode', $parentSku)
                                ->orWhere('U_OLD_SKU', $parentSku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->first();
                } catch (ClientException $exception) {
                    $logger->writeSapLog($exception);
                }

                if (isset($item)) {
                    $shopeePriceUpdate = new ShopeeService('/product/update_price', 'shop', $shopeeToken->access_token);
                    $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
                        'item_id' => (int) $productId,
                        'price_list' => [
                            [
                                'model_id' => (int) 0,
                                'original_price' => (float) $item['ItemPrices'][9]['Price']
                            ]
                        ]
                    ]);

                    $shopeePriceUpdateResponseArr = $logger->validateResponse(json_decode($shopeePriceUpdateResponse->body(), true));

                    if ($shopeePriceUpdateResponseArr) {
                        $successCount++;
                        $logger->writeLog("The product's price with {$parentSku} parent SKU was updated.");
                    }
                }             
            }
        }

        $logger->writeLog("Updated a total of {$successCount} Shopee price.");

        return response()->json($this->getJsonResponse($successCount, $logger->getErrorCount(), 'Shopee price', 'updated'));
    }

    public function updateStock()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('stock_update'); 

        $logger->writeLog('EXECUTING SHOPEE UPDATE STOCK SCRIPT . . .');

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

        $logger->writeLog("Updating Shopee product stock . . .");

        foreach ($productList as $product) {
            $itemSapService = new SapService();

            $parentSku = $product['item_sku'];
            $productId = $product['item_id'];           

            // retrieve the model if it's applicable to the current product
            if ($product['has_model']) {
                $logger->writeLog('Retrieving product models . . .');

                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = $logger->validateResponse(json_decode($shopeeModelsResponse->body(), true));

                if ($shopeeModelsResponseArr) {
                    foreach ($shopeeModelsResponseArr['response']['model'] as $key => $model) { 
                        $modelId = $model['model_id'];
                        $sku = $model['model_sku'];

                        try {
                            $item = $itemSapService->getOdataClient()
                            ->select('ItemCode', 'QuantityOnStock', 'InventoryItem')
                            ->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                        } catch (ClientException $exception) {
                            $logger->writeSapLog($exception);
                        }

                        if (isset($item)) {
                            if ($item['InventoryItem'] == 'tYES') {
                                $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $shopeeToken->access_token);
                                $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
                                    'item_id' => (int) $productId,
                                    'stock_list' => [
                                        [
                                            'model_id' => (int) $modelId,
                                            'normal_stock' => (int) $item['QuantityOnStock']
                                        ]
                                    ]
                                ]);

                                $shopeeStockUpdateResponseArr = $logger->validateResponse(json_decode($shopeeStockUpdateResponse->body(), true));
                                
                                if ($shopeeStockUpdateResponseArr) {
                                    $successCount++;
                                    $logger->writeLog("The product's stock with {$sku} variant SKU was updated.");
                                }
                            }
                        }
                    }
                }
            } else {
                try {
                    $item = $itemSapService->getOdataClient()
                        ->select('ItemCode', 'QuantityOnStock', 'InventoryItem')
                        ->from('Items')
                        ->whereNested(function($query) use ($parentSku) {
                            $query->where('ItemCode', $parentSku)
                                ->orWhere('U_OLD_SKU', $parentSku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->first();
                } catch (ClientException $exception) {
                    $logger->writeSapLog($exception);
                }

                if (isset($item)) {
                    if ($item['InventoryItem'] == 'tYES') {
                        $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $shopeeToken->access_token);
                        $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
                            'item_id' => (int) $productId,
                            'stock_list' => [
                                [
                                    'model_id' => (int) 0,
                                    'normal_stock' => (int) $item['QuantityOnStock']
                                ]
                            ]
                        ]);

                        $shopeeStockUpdateResponseArr = $logger->validateResponse(json_decode($shopeeStockUpdateResponse->body(), true));

                        if ($shopeeStockUpdateResponseArr) {
                            $successCount++;
                            $logger->writeLog("The product's stock with {$parentSku} parent SKU was updated.");
                        }
                    }
                }
            }
        }

        $logger->writeLog("Updated a total of {$successCount} Shopee stock.");

        return response()->json($this->getJsonResponse($successCount, $logger->getErrorCount(), 'Shopee stock', 'updated'));
    }

    public function generateSalesorder()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('salesorder_generate'); 

        $logger->writeLog('EXECUTING SAP GENERATE SALES ORDER SCRIPT . . .');

        $detailedOrderList = [];
        $moreReadyOrders = true;
        $offset = 0;
        $pageSize = 50;

        $logger->writeLog('Retrieving Shopee orders . . .');

        while ($moreReadyOrders) {
            $orderList = [];

            $shopeeReadyOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeToken->access_token);
            $shopeeReadyOrdersResponse = Http::get($shopeeReadyOrders->getFullPath(), array_merge([
                // 'time_range_field' => 'update_time',
                // 'time_from' => strtotime(date("Y-m-d 00:00:00")),
                // 'time_to' => strtotime(date("Y-m-d 23:59:59")),
                // testing
                'time_range_field' => 'create_time',
                'time_from' => 1626735608,
                'time_to' => 1627686008,
                'page_size' => $pageSize,
                'cursor' => $offset,
                'order_status' => 'SHIPPED',
                // 'order_status' => 'READY_TO_SHIP',
                'response_optional_fields' => 'order_status'
            ], $shopeeReadyOrders->getShopCommonParameter()));

            $shopeeReadyOrdersResponseArr = $logger->validateResponse(json_decode($shopeeReadyOrdersResponse->body(), true));

            if ($shopeeReadyOrdersResponseArr) {
                if (array_key_exists('order_list', $shopeeReadyOrdersResponseArr['response'])) {
                    foreach ($shopeeReadyOrdersResponseArr['response']['order_list'] as $order) {
                        array_push($orderList, $order['order_sn']);
                    }
                    
                    $shopeeOrderDetail = new ShopeeService('/order/get_order_detail', 'shop', $shopeeToken->access_token);
                    $shopeeOrderDetailResponse = Http::get($shopeeOrderDetail->getFullPath(), array_merge([
                        'order_sn_list' => implode(",", $orderList),
                        'response_optional_fields' => 'total_amount,item_list,buyer_user_id,buyer_username,recipient_address,estimated_shipping_fee,actual_shipping_fee,actual_shipping_fee_confirmed'
                    ], $shopeeOrderDetail->getShopCommonParameter()));

                    $shopeeOrderDetailResponseArr = $logger->validateResponse(json_decode($shopeeOrderDetailResponse->body(), true));

                    if ($shopeeOrderDetailResponseArr) {
                        foreach ($shopeeOrderDetailResponseArr['response']['order_list'] as $order) {
                            array_push($detailedOrderList, $order);
                        }  
                    }
                }

                if ($shopeeReadyOrdersResponseArr['response']['more']) {
                    $offset += $pageSize;
                } else {
                    $moreReadyOrders = false;
                }
            } else {
                break;
            }
        }

        $logger->writeLog("Retrieved a total of " . count($detailedOrderList) . " Shopee orders.");

        $salesOrderSapService = new SapService();
        
        $logger->writeLog("Retrieving the Shopee order default values . . .");

        try {
            $ecm = $salesOrderSapService->getOdataClient()
                ->from('U_ECM')
                ->get();
        } catch (ClientException $exception) {
            $logger->writeSapLog($exception);
        }

        if (isset($ecm)) {
            foreach ($ecm as $ecmItem) {
                if ($ecmItem['properties']['Code'] == 'SHOPEE_CUSTOMER') {
                    $shopeeCust = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'TAX_CODE') {
                    $taxCode = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'PERCENTAGE') {
                    $taxPercentage = (float) $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'SHIPPING_FEE') {
                    $shippingItem = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'SHOP_VOUCHER') {
                    $shopVoucherItem = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'SELLER_VOUCHER') {
                    $sellerVoucherItem = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'SHOPEE_COIN') {
                    $shopeeCoinItem = $ecmItem['properties']['Name'];
                } 
            }
        }

        $successCount = 0;

        $logger->writeLog("Generating SAP B1 Sales Order . . .");

        foreach ($detailedOrderList as $order) {
            try {
                $existedSO = $salesOrderSapService->getOdataClient()
                    ->select('DocNum')
                    ->from('Orders')
                    ->where('U_Order_ID', (string)$order['order_sn'])
                    ->where('CancelStatus', 'csNo')
                    ->first();
            } catch (ClientException $exception) {
                $logger->writeSapLog($exception);
            }
            
            if (isset($existedSO)) {
                $itemList = [];

                foreach ($order['item_list'] as $item) {         
                    $sku = $item['model_sku'] ? $item['model_sku'] : $item['item_sku'];

                    try {
                        $sapItemResponse = $salesOrderSapService->getOdataClient()
                            ->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                    } catch (ClientException $exception) {
                        $logger->writeSapLog($exception);
                    }

                    if (isset($sapItemResponse)) {
                        $sapItem = $sapItemResponse['properties'];

                        $itemList[] = [
                            'ItemCode' => $sapItem['ItemCode'],
                            'Quantity' => $item['model_quantity_purchased'],
                            'VatGroup' => $taxCode,
                            'UnitPrice' => $item['model_discounted_price'] / $taxPercentage
                        ];
                    }
                }

                try {
                    $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', [
                        'CardCode' => $shopeeCust,
                        'NumAtCard' => $order['order_sn'],
                        'DocDate' => date('Y-m-d', $order['create_time']),
                        'DocDueDate' => date('Y-m-d', $order['ship_by_date']),
                        'TaxDate' => date('Y-m-d', $order['create_time']),
                        'U_Ecommerce_Type' => 'Shopee',
                        'U_Order_ID' => $order['order_sn'],
                        'U_Customer_Name' => $order['recipient_address']['name'],
                        'U_Customer_Phone' => $order['recipient_address']['phone'],
                        'U_Customer_Shipping_Address' => $order['recipient_address']['full_address'],
                        'DocTotal' => $order['total_amount'],
                        'DocumentLines' => $itemList
                    ]);
                } catch (ClientException $exception) {
                    $logger->writeSapLog($exception);
                }

                if (isset($salesOrder)) {
                    $successCount++;
                    $logger->writeLog("SAP B1 sales order with {$order['order_sn']} Shopee order ID was generated.");
                }
            }      
        }

        return response()->json($this->getJsonResponse($successCount, $logger->getErrorCount(), 'sales orders', 'generated'));

        // OLDDDDDDDDD
        // $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        // $orderList = [];
        // $moreReadyOrders = true;
        // $offset = 0;
        // $pageSize = 50;
        
        // while ($moreReadyOrders) {
        //     $shopeeReadyOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeToken->access_token);
        //     $shopeeReadyOrdersResponse = Http::get($shopeeReadyOrders->getFullPath(), array_merge([
        //         'time_range_field' => 'update_time',
        //         'time_from' => strtotime(date("Y-m-d 00:00:00")),
        //         'time_to' => strtotime(date("Y-m-d 23:59:59")),
        //         'page_size' => $pageSize,
        //         'cursor' => $offset,
        //         'order_status' => 'READY_TO_SHIP',
        //         'response_optional_fields' => 'order_status'
        //     ], $shopeeReadyOrders->getShopCommonParameter()));

        //     $shopeeReadyOrdersResponseArr = json_decode($shopeeReadyOrdersResponse->body(), true);

        //     foreach ($shopeeReadyOrdersResponseArr['response']['order_list'] as $order) {
        //         array_push($orderList, $order['order_sn']);
        //     }

        //     if ($shopeeReadyOrdersResponseArr['response']['more']) {
        //         $offset += $pageSize;
        //     } else {
        //         $moreReadyOrders = false;
        //     }   
        // }
        // // dd($orderList);
        // $orderStr = implode(",", $orderList);
        // // for testing
        // // $orderStr = '18033000113B04H';
        
        // $shopeeOrderDetail = new ShopeeService('/order/get_order_detail', 'shop', $shopeeToken->access_token);
        // $shopeeOrderDetailResponse = Http::get($shopeeOrderDetail->getFullPath(), array_merge([
        //     'order_sn_list' => $orderStr,
        //     'response_optional_fields' => 'total_amount,item_list,buyer_user_id,buyer_username,recipient_address,estimated_shipping_fee,actual_shipping_fee,actual_shipping_fee_confirmed'
        // ], $shopeeOrderDetail->getShopCommonParameter()));

        // $shopeeOrderDetailResponseArr = json_decode($shopeeOrderDetailResponse->body(), true);
        // $orderListDetails = $shopeeOrderDetailResponseArr['response']['order_list'];

        // $salesOrderSapService = new SapService();

        // $salesOrderList = [];
        // $ecm = $salesOrderSapService->getOdataClient()->from('U_ECM')->get();

        // foreach ($ecm as $ecmItem) {
        //     if ($ecmItem['properties']['Code'] == 'SHOPEE_CUSTOMER') {
        //         $shopeeCust = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'TAX_CODE') {
        //         $taxCode = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'PERCENTAGE') {
        //         $taxPercentage = (float) $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'SHIPPING_FEE') {
        //         $shippingItem = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'SHOP_VOUCHER') {
        //         $shopVoucherItem = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'SELLER_VOUCHER') {
        //         $sellerVoucherItem = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'SHOPEE_COIN') {
        //         $shopeeCoinItem = $ecmItem['properties']['Name'];
        //     } 
        // }
        
        // foreach ($orderListDetails as $order) {
        //     $existedSO = $salesOrderSapService->getOdataClient()
        //         ->select('DocNum')
        //         ->from('Orders')
        //         ->where('U_Order_ID', (string)$order['order_sn'])
        //         ->where('CancelStatus', 'csNo')
        //         ->first();

        //     if (!$existedSO) {
        //         $escrowDetail = new ShopeeService('/payment/get_escrow_detail', 'shop', $shopeeToken->access_token);
        //         $escrowDetailResponse = Http::get($escrowDetail->getFullPath(), array_merge([
        //             'order_sn' => $order['order_sn']
        //         ], $escrowDetail->getShopCommonParameter()));
        //         $escrowDetailResponseArr = json_decode($escrowDetailResponse->body(), true);
        //         $escrow = $escrowDetailResponseArr['response'];

        //         $itemList = [];

        //         foreach ($order['item_list'] as $item) {                   
        //             try {
        //                 $response = $salesOrderSapService->getOdataClient()
        //                     ->from('Items')
        //                     ->where('U_SH_ITEM_CODE', (string)$item['item_id'])
        //                     ->where('U_SH_INTEGRATION', 'Yes')
        //                     ->first();
        //             } catch(ClientException $e) {
        //                 dd($e->getResponse()->getBody()->getContents());
        //             }

        //             $sapItem = $response['properties'];

        //             $itemList[] = [
        //                 'ItemCode' => $sapItem['ItemCode'],
        //                 'Quantity' => $item['model_quantity_purchased'],
        //                 'VatGroup' => $taxCode,
        //                 'UnitPrice' => $item['model_discounted_price'] / $taxPercentage
        //             ];
        //         }

        //         if ($escrow['order_income']['buyer_paid_shipping_fee']) {
        //             $itemList[] = [
        //                 'ItemCode' => $shippingItem,
        //                 'Quantity' => 1,
        //                 'VatGroup' => $taxCode,
        //                 'UnitPrice' => $escrow['order_income']['buyer_paid_shipping_fee'] / $taxPercentage
        //             ];           
        //         }

        //         if ($escrow['order_income']['voucher_from_shopee']) {
        //             $itemList[] = [
        //                 'ItemCode' => $shopVoucherItem,
        //                 'Quantity' => -1,
        //                 'VatGroup' => $taxCode,
        //                 'UnitPrice' => $escrow['order_income']['voucher_from_shopee'] / $taxPercentage
        //             ];           
        //         }

        //         if ($escrow['order_income']['voucher_from_seller']) {
        //             $itemList[] = [
        //                 'ItemCode' => $sellerVoucherItem,
        //                 'Quantity' => -1,
        //                 'VatGroup' => $taxCode,
        //                 'UnitPrice' => $escrow['order_income']['voucher_from_seller'] / $taxPercentage
        //             ];           
        //         }
                
        //         if ($escrow['order_income']['coins']) {
        //             $itemList[] = [
        //                 'ItemCode' => $shopeeCoinItem,
        //                 'Quantity' => -1,
        //                 'VatGroup' => $taxCode,
        //                 'UnitPrice' => $escrow['order_income']['coins'] / $taxPercentage
        //             ];           
        //         }

        //         $salesOrderList = [
        //             'CardCode' => $shopeeCust,
        //             'NumAtCard' => $order['order_sn'],
        //             'DocDate' => date('Y-m-d', $order['create_time']),
        //             'DocDueDate' => date('Y-m-d', $order['ship_by_date']),
        //             'TaxDate' => date('Y-m-d', $order['create_time']),
        //             'U_Ecommerce_Type' => 'Shopee',
        //             'U_Order_ID' => $order['order_sn'],
        //             'U_Customer_Name' => $order['recipient_address']['name'],
        //             'U_Customer_Phone' => $order['recipient_address']['phone'],
        //             'U_Customer_Shipping_Address' => $order['recipient_address']['full_address'],
        //             'DocTotal' => $order['total_amount'],
        //             'DocumentLines' => $itemList
        //         ];

        //         $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);

        //         if ($salesOrder) {
        //             return response()->json(null, 200);
        //         } else {
        //             return response()->json(null, 500);
        //         }
        //     }        
        // }

    }

    public function generateInvoice()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('invoice_generate'); 

        $logger->writeLog('EXECUTING SAP GENERATE INVOICE SCRIPT . . .');

        $orderList = [];
        $moreShippedOrders = true;
        $offset = 0;
        $pageSize = 50;

        $logger->writeLog('Retrieving Shopee orders . . .');

        while ($moreShippedOrders) {
            $shopeeShippedOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeToken->access_token);
            $shopeeShippedOrdersResponse = Http::get($shopeeShippedOrders->getFullPath(), array_merge([
                // 'time_range_field' => 'update_time',
                // 'time_from' => strtotime(date("Y-m-d 00:00:00")),
                // 'time_to' => strtotime(date("Y-m-d 23:59:59")),
                // testing
                'time_range_field' => 'create_time',
                'time_from' => 1626735608,
                'time_to' => 1627686008,
                'page_size' => $pageSize,
                'cursor' => $offset,
                'order_status' => 'SHIPPED',
                'response_optional_fields' => 'order_status'
            ], $shopeeShippedOrders->getShopCommonParameter()));

            $shopeeShippedOrdersResponseArr = $logger->validateResponse(json_decode($shopeeShippedOrdersResponse->body(), true));

            if ($shopeeShippedOrdersResponseArr) {
                if (array_key_exists('order_list', $shopeeShippedOrdersResponseArr['response'])) {
                    foreach ($shopeeShippedOrdersResponseArr['response']['order_list'] as $order) {
                        array_push($orderList, $order['order_sn']);
                    }
                }

                if ($shopeeShippedOrdersResponseArr['response']['more']) {
                    $offset += $pageSize;
                } else {
                    $moreShippedOrders = false;
                }  
            } else {
                break;
            }
        }

        $invoiceSapService = new SapService();

        $successCount = 0;
        
        $logger->writeLog("Generating SAP B1 A/R Invoice . . .");

        foreach ($orderList as $order) {
            try {
                $salesOrder = $invoiceSapService->getOdataClient()
                    ->from('Orders')
                    ->where('U_Order_ID', (string)$order)
                    ->where('DocumentStatus', 'bost_Open')
                    ->where('CancelStatus', 'csNo')
                    ->first();
            } catch (ClientException $exception) {
                $logger->writeSapLog($exception);
            }
            
            try {
                $existInv = $invoiceSapService->getOdataClient()
                    ->from('Invoices')
                    ->where('U_Order_ID', (string)$order)
                    ->first();
            } catch (ClientException $exception) {
                $logger->writeSapLog($exception);
            }
            
            if (isset($salesOrder) && isset($existInv)) {
                if ($salesOrder && !$existInv) {
                    $itemList = [];

                    foreach ($salesOrder['DocumentLines'] as $itemLine => $item) {
                        $batchList = [];

                        if ($item['BatchNumbers']) {                  
                            foreach ($item['BatchNumbers'] as $batch) {
                                $batchList[] = [
                                    'BatchNumber' => $batch['BatchNumber'],
                                    'Quantity' => $batch['Quantity']
                                ];
                            }
                        }

                        $itemList[] = [
                            'BaseType' => 17,
                            'BaseEntry' => $salesOrder['DocEntry'],
                            'BaseLine' => $itemLine,
                            'BatchNumbers' => $batchList
                        ];
                    }

                    try {
                        $invoice = $invoiceSapService->getOdataClient()->post('Invoices', [
                            'CardCode' => $salesOrder['CardCode'],
                            'NumAtCard' => $salesOrder['NumAtCard'],
                            'DocDate' => $salesOrder['DocDate'],
                            'DocDueDate' => $salesOrder['DocDueDate'],
                            'TaxDate' => $salesOrder['TaxDate'],
                            'U_Ecommerce_Type' => $salesOrder['U_Ecommerce_Type'],
                            'U_Order_ID' => $salesOrder['U_Order_ID'],
                            'U_Customer_Name' => $salesOrder['U_Customer_Name'],
                            'U_Customer_Phone' => $salesOrder['U_Customer_Phone'],
                            'U_Customer_Shipping_Address' => $salesOrder['U_Customer_Shipping_Address'],
                            'DocTotal' => $salesOrder['DocTotal'],
                            'DocumentLines' => $itemList
                        ]);
                    } catch (ClientException $exception) {
                        $logger->writeSapLog($exception);
                    }
    
                    if (isset($invoice)) {
                        $successCount++;
                        $logger->writeLog("SAP B1 A/R invoice with {$salesOrder['U_Order_ID']} Shopee order ID was generated.");
                    }
                }
            }    
        }
            
        return response()->json($this->getJsonResponse($successCount, $logger->getErrorCount(), 'A/R invoices', 'generated'));
        




        //OLDDDDD
        // $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        // $orderList = [];
        // $moreShippedOrders = true;
        // $offset = 0;
        // $pageSize = 50;
        
        // while ($moreShippedOrders) {
        //     $shopeeShippedOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeToken->access_token);
        //     $shopeeShippedOrdersResponse = Http::get($shopeeShippedOrders->getFullPath(), array_merge([
        //         'time_range_field' => 'update_time',
        //         'time_from' => strtotime(date("Y-m-d 00:00:00")),
        //         'time_to' => strtotime(date("Y-m-d 23:59:59")),
        //         // 'time_from' => 1627833600,
        //         // 'time_to' => 1627919999,
        //         'page_size' => $pageSize,
        //         'cursor' => $offset,
        //         'order_status' => 'SHIPPED',
        //         'response_optional_fields' => 'order_status'
        //     ], $shopeeShippedOrders->getShopCommonParameter()));

        //     $shopeeShippedOrdersResponseArr = json_decode($shopeeShippedOrdersResponse->body(), true);

        //     foreach ($shopeeShippedOrdersResponseArr['response']['order_list'] as $order) {
        //         array_push($orderList, $order['order_sn']);
        //     }

        //     if ($shopeeShippedOrdersResponseArr['response']['more']) {
        //         $offset += $pageSize;
        //     } else {
        //         $moreShippedOrders = false;
        //     }   
        // }

        // // for testing
        // // $orderList = ['210803K1WFX89R'];

        // $invoiceSapService = new SapService();
        // $invoiceList = [];

        // foreach ($orderList as $order) {

        //     $salesOrder = $invoiceSapService->getOdataClient()
        //         ->from('Orders')
        //         ->where('U_Order_ID', (string)$order)
        //         ->where('DocumentStatus', 'bost_Open')
        //         ->where('CancelStatus', 'csNo')
        //         ->first();

        //     $existInv = $invoiceSapService->getOdataClient()
        //         ->from('Invoices')
        //         ->where('U_Order_ID', (string)$order)
        //         ->first();

        //     if ($salesOrder && !$existInv) {
        //         $itemList = [];

        //         foreach ($salesOrder['DocumentLines'] as $itemLine => $item) {
        //             $batchList = [];

        //             if ($item['BatchNumbers']) {                  
        //                 foreach ($item['BatchNumbers'] as $batch) {
        //                     $batchList[] = [
        //                         'BatchNumber' => $batch['BatchNumber'],
        //                         'Quantity' => $batch['Quantity']
        //                     ];
        //                 }
        //             }

        //             $itemList[] = [
        //                 'BaseType' => 17,
        //                 'BaseEntry' => $salesOrder['DocEntry'],
        //                 'BaseLine' => $itemLine,
        //                 'BatchNumbers' => $batchList
        //             ];
        //         }

        //         $invoiceList = [
        //             'CardCode' => $salesOrder['CardCode'],
        //             'NumAtCard' => $salesOrder['NumAtCard'],
        //             'DocDate' => $salesOrder['DocDate'],
        //             'DocDueDate' => $salesOrder['DocDueDate'],
        //             'TaxDate' => $salesOrder['TaxDate'],
        //             'U_Ecommerce_Type' => $salesOrder['U_Ecommerce_Type'],
        //             'U_Order_ID' => $salesOrder['U_Order_ID'],
        //             'U_Customer_Name' => $salesOrder['U_Customer_Name'],
        //             'U_Customer_Phone' => $salesOrder['U_Customer_Phone'],
        //             'U_Customer_Shipping_Address' => $salesOrder['U_Customer_Shipping_Address'],
        //             'DocTotal' => $salesOrder['DocTotal'],
        //             'DocumentLines' => $itemList
        //         ];

        //         $invoice = $invoiceSapService->getOdataClient()->post('Invoices', $invoiceList);

        //         if ($invoice) {
        //             return response()->json(null, 200);
        //         } else {
        //             return response()->json(null, 500);
        //         }
        //     }
        // }



    }

    public function generateCreditmemo()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first(); 
        $logger = new LogService('creditmemo_generate'); 

        $logger->writeLog('EXECUTING SAP GENERATE CREDIT MEMO SCRIPT . . .');

        $returnList = [];
        $moreReturnItem = true;
        $offset = 0;
        $pageSize = 50;

        $logger->writeLog('Retrieving Shopee returns . . .');

        while ($moreReturnItem) {
            $shopeeReturnItems = new ShopeeService('/returns/get_return_list', 'shop', $shopeeToken->access_token);
            $shopeeReturnItemsResponse = Http::get($shopeeReturnItems->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'page_no' => $offset
            ], $shopeeReturnItems->getShopCommonParameter()));

            $shopeeReturnItemsResponseArr = $logger->validateResponse(json_decode($shopeeReturnItemsResponse->body(), true));

            if ($shopeeReturnItemsResponseArr) {
                if (array_key_exists('return', $shopeeReturnItemsResponseArr['response'])) {
                    foreach ($shopeeReturnItemsResponseArr['response']['return'] as $return) {
                        array_push($returnList, $return);
                    }
                }

                if ($shopeeReturnItemsResponseArr['response']['more']) {
                    $offset += $pageSize;
                } else {
                    $moreReturnItem = false;
                }
            } else {
                break;
            }
        }

        $logger->writeLog("Retrieved a total of " . count($returnList) . " Shopee returns.");

        $returnSapService = new SapService();
        
        $logger->writeLog("Retrieving the Shopee item list default values . . .");

        try {
            $ecm = $returnSapService->getOdataClient()
                ->from('U_ECM')
                ->get();
        } catch (ClientException $exception) {
            $logger->writeSapLog($exception);
        }

        if (isset($ecm)) {
            foreach ($ecm as $ecmItem) {
                if ($ecmItem['properties']['Code'] == 'SHOPEE_CUSTOMER') {
                    $shopeeCust = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'SELLER_PAYMENT') {
                    $sellerPaymentItem = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'TAX_CODE') {
                    $taxCode = $ecmItem['properties']['Name'];
                } elseif ($ecmItem['properties']['Code'] == 'PERCENTAGE') {
                    $taxPercentage = (float) $ecmItem['properties']['Name'];
                }
            }
        }

        $successCount = 0;

        $logger->writeLog("Generating SAP B1 Credit Memo . . .");

        foreach ($returnList as $returnItem) {
            try {
                $order = $returnSapService->getOdataClient()
                    ->from('Orders')
                    ->where('U_Order_ID', (string)$returnItem['order_sn'])
                    ->where('CancelStatus', 'csNo')
                    ->first();
            } catch (ClientException $exception) {
                $logger->writeSapLog($exception);
            }
            
            if (isset($order) && $returnItem['status'] == 'REFUND_PAID') {
                $itemList = [];

                foreach ($returnItem['item'] as $item) {
                    $sku = $item['variation_sku'] ? $item['variation_sku'] : $item['item_sku'];

                    try {
                        $sapItemResponse = $returnSapService->getOdataClient()
                            ->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                    } catch(ClientException $exception) {
                        $logger->writeSapLog($exception);
                    }

                    if (isset($sapItemResponse)) {
                        $sapItem = $sapItemResponse['properties'];

                        $itemList[] = [
                            'ItemCode' => $sapItem['ItemCode'],
                            'Quantity' => $item['amount'],
                            'VatGroup' => $taxCode,
                            'UnitPrice' => $item['item_price'] / $taxPercentage
                        ];
                    }
                }

                $escrowDetail = new ShopeeService('/payment/get_escrow_detail', 'shop', $shopeeToken->access_token);
                $escrowDetailResponse = Http::get($escrowDetail->getFullPath(), array_merge([
                    'order_sn' => $returnItem['order_sn']
                ], $escrowDetail->getShopCommonParameter()));

                $escrowDetailResponseArr = $logger->validateResponse(json_decode($escrowDetailResponse->body(), true));

                if ($escrowDetailResponseArr) {
                    if (array_key_exists('order_income', $escrowDetailResponseArr['response'])) {
                        $escrow = $escrowDetailResponseArr['response']['order_income'];
                        $docTotal = $escrow['original_cost_of_goods_sold'] - $escrow['original_shopee_discount'];
                        $sellerPayment = $docTotal - $escrow['seller_return_refund'];
                        $qty = $sellerPayment < 0 ? 1 : -1;

                        if ($sellerPayment) {
                            $itemList[] = [
                                'ItemCode' => $sellerPaymentItem,
                                'Quantity' => $qty,
                                'VatGroup' => $taxCode,
                                'UnitPrice' => abs($sellerPayment) / $taxPercentage
                            ];
                        }

                        try {
                            $creditMemo = $returnSapService->getOdataClient()->post('CreditNotes', [
                                'CardCode' => $shopeeCust,
                                'NumAtCard' => $returnItem['return_sn'],
                                'DocDate' => $returnItem['create_time'],
                                'DocDueDate' => $returnItem['return_seller_due_date'],
                                'TaxDate' => $returnItem['create_time'],
                                'U_Ecommerce_Type' => 'Shopee',
                                'U_Order_ID' => $returnItem['order_sn'],
                                'U_Customer_Name' => $order['U_Customer_Name'],
                                'U_Customer_Phone' => $order['U_Customer_Phone'],
                                'U_Customer_Email' => $returnItem['user']['email'],
                                'U_Customer_Shipping_Address' => $order['U_Customer_Shipping_Address'],
                                'DocTotal' => $returnItem['refund_amount'],
                                'DocumentLines' => $itemList
                            ]);
                        } catch (ClientException $exception) {
                            $logger->writeSapLog($exception);
                        }
        
                        if (isset($creditMemo)) {
                            $successCount++;
                            $logger->writeLog("SAP B1 Credit Memo with {$returnItem['return_sn']} Shopee return ID was generated.");
                        }
                    }
                }
            }
        }

        return response()->json($this->getJsonResponse($successCount, $logger->getErrorCount(), 'Credit Memos', 'generated'));




        //OLDDDDD
        // $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        // $returnList = [];
        // $moreReturnItem = true;
        // $offset = 0;
        // $pageSize = 50;

        // while ($moreReturnItem) {
        //     $shopeeReturnItems = new ShopeeService('/returns/get_return_list', 'shop', $shopeeToken->access_token);
        //     $shopeeReturnItemsResponse = Http::get($shopeeReturnItems->getFullPath(), array_merge([
        //         'page_size' => $pageSize,
        //         'page_no' => $offset
        //     ], $shopeeReturnItems->getShopCommonParameter()));

        //     $shopeeReturnItemsResponseArr = json_decode($shopeeReturnItemsResponse->body(), true);

        //     foreach ($shopeeReturnItemsResponseArr['response']['return'] as $return) {
        //         array_push($returnList, $return);
        //     }

        //     if ($shopeeReturnItemsResponseArr['response']['more']) {
        //         $offset += $pageSize;
        //     } else {
        //         $moreReturnItem = false;
        //     }
        // }

        // // for testing
        // // foreach ($returnList as $value) {
        // //     if ($value['return_sn'] == '200124144731506') {
        // //     // if ($value['return_sn'] == '190923173135204') {
        // //         $returnList = [];
        // //         array_push($returnList, $value);
        // //     }
        // // }

        // $itemList = [];
        // $returnSapService = new SapService();

        // $ecm = $returnSapService->getOdataClient()->from('U_ECM')->get();

        // foreach ($ecm as $ecmItem) {
        //     if ($ecmItem['properties']['Code'] == 'SHOPEE_CUSTOMER') {
        //         $shopeeCust = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'SELLER_PAYMENT') {
        //         $sellerPaymentItem = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'TAX_CODE') {
        //         $taxCode = $ecmItem['properties']['Name'];
        //     } elseif ($ecmItem['properties']['Code'] == 'PERCENTAGE') {
        //         $taxPercentage = (float) $ecmItem['properties']['Name'];
        //     }
        // }

        // // dd($returnList);
        // foreach ($returnList as $returnItem) {
        //     $order = $returnSapService->getOdataClient()
        //         ->from('Orders')
        //         ->where('U_Order_ID', (string)$returnItem['order_sn'])
        //         ->where('CancelStatus', 'csNo')
        //         ->first();

        //     if ($order && $returnItem['status'] == 'REFUND_PAID') {
        //         foreach ($returnItem['item'] as $item) {
        //             try {
        //                 $response = $returnSapService->getOdataClient()
        //                     ->from('Items')
        //                     ->where('U_SH_ITEM_CODE', (string)$item['item_id'])
        //                     ->where('U_SH_INTEGRATION', 'Yes')
        //                     ->first();
        //             } catch(ClientException $e) {
        //                 dd($e->getResponse()->getBody()->getContents());
        //             }

        //             $sapItem = $response['properties'];

        //             $itemList[] = [
        //                 'ItemCode' => $sapItem['ItemCode'],
        //                 'Quantity' => $item['amount'],
        //                 'VatGroup' => $taxCode,
        //                 'UnitPrice' => $item['item_price'] / $taxPercentage
        //             ];
        //         }

        //         $escrowDetail = new ShopeeService('/payment/get_escrow_detail', 'shop', $shopeeToken->access_token);
        //         $escrowDetailResponse = Http::get($escrowDetail->getFullPath(), array_merge([
        //             'order_sn' => $returnItem['order_sn']
        //         ], $escrowDetail->getShopCommonParameter()));
        //         $escrowDetailResponseArr = json_decode($escrowDetailResponse->body(), true);
        //         $escrow = $escrowDetailResponseArr['response']['order_income'];
        //         // dd($escrow);
        //         $docTotal = $escrow['original_cost_of_goods_sold'] - $escrow['original_shopee_discount'];
        //         $sellerPayment = $docTotal - $escrow['seller_return_refund'];
        //         $qty = $sellerPayment < 0 ? 1 : -1;

        //         if ($sellerPayment) {
        //             $itemList[] = [
        //                 'ItemCode' => $sellerPaymentItem,
        //                 'Quantity' => $qty,
        //                 'VatGroup' => $taxCode,
        //                 'UnitPrice' => abs($sellerPayment) / $taxPercentage
        //             ];
        //         }

        //         $creditMemoList = [
        //             'CardCode' => $shopeeCust,
        //             'NumAtCard' => $returnItem['return_sn'],
        //             'DocDate' => $returnItem['create_time'],
        //             'DocDueDate' => $returnItem['return_seller_due_date'],
        //             'TaxDate' => $returnItem['create_time'],
        //             'U_Ecommerce_Type' => 'Shopee',
        //             'U_Order_ID' => $returnItem['order_sn'],
        //             'U_Customer_Name' => $order['U_Customer_Name'],
        //             'U_Customer_Phone' => $order['U_Customer_Phone'],
        //             'U_Customer_Email' => $returnItem['user']['email'],
        //             'U_Customer_Shipping_Address' => $order['U_Customer_Shipping_Address'],
        //             'DocTotal' => $returnItem['refund_amount'],
        //             'DocumentLines' => $itemList
        //         ];
        //         // dd($creditMemoList);
        //         $creditMemo = $returnSapService->getOdataClient()->post('CreditNotes', $creditMemoList);

        //         if ($creditMemo) {
        //             return response()->json(null, 200);
        //         } else {
        //             return response()->json(null, 500);
        //         }
        //     }
        // }
    }

    public function index2()
    {
        return view('lazada.dashboard');
    }
}