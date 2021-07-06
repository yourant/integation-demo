<?php

namespace App\Console\Commands;

use App\Services\SapService;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;

class ShopeeFirstScheduler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:first-scheduler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute the first scheduler command consisting of Item Master, Price and Stock integration';

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
        $shopeeAccess = new ShopeeService('/auth/token/get', 'public');

        $accessResponse = Http::post($shopeeAccess->getFullPath() . $shopeeAccess->getAccessTokenQueryString(), [
            'code' => $shopeeAccess->getCode(),
            'partner_id' => $shopeeAccess->getPartnerId(),
            'shop_id' => $shopeeAccess->getShopId()
        ]);
        
        $accessResponseArr = json_decode($accessResponse->body(), true);
        $shopeeAccess->setAccessToken($accessResponseArr['access_token']);
        
        // retrieve products with base
        $productList = [];
        $moreProducts = true;
        $offset = 0;
        $pageSize = 50;
        
        while ($moreProducts) {
            $productSegmentList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeAccess->getAccessToken());
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'NORMAL'
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = json_decode($shopeeProductsResponse->body(), true);
            // dd($shopeeProductsResponseArr);
            foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                array_push($productSegmentList, $item['item_id']);
            }

            $productStr = implode(",", $productSegmentList);
            
            $shopeeProductBase = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeAccess->getAccessToken());
            $shopeeProductBaseResponse = Http::get($shopeeProductBase->getFullPath(), array_merge([
                'item_id_list' => $productStr
            ], $shopeeProductBase->getShopCommonParameter()));
            
            $shopeeProductBaseResponseArr = json_decode($shopeeProductBaseResponse->body(), true);
            // dd($shopeeProductBaseResponseArr);
            $productList = array_merge($productList, $shopeeProductBaseResponseArr['response']['item_list']);

            if ($shopeeProductsResponseArr['response']['has_next_page']) {
                $offset += $pageSize;
            } else {
                $moreProducts = false;
            }   
        }

        // list   
        
        foreach ($productList as $product) {
            $sku = $product['item_sku'];
            $shItemCode = $product['item_id'];
            // $shPrice = $product['price_info']['current_price'];
            // $shStock = $product['stock_info']['current_stock'];

            if ($product['has_model']) {
                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeAccess->getAccessToken());
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = json_decode($shopeeModelsResponse->body(), true);
                // dd($shopeeModelsResponseArr['response']['model']);
                foreach ($shopeeModelsResponseArr['response']['model'] as $key => $model) {                                
                    $shItemId = $product['item_id'];
                    $sku = $model['model_sku'];
                    $shItemCode = $model['model_id'];
                    // $shPrice = $model['price_info']['current_price'];
                    // foreach ($model['stockinfo'] as $stock) {
                    //     if ($stock['stock_type'] == 2) {
                    //         $shStock = $stock['current_stock'];
                    //     }
                    // }

                    try {
                        $itemSapService = new SapService();
                        // ('U_SH_ITEM_CODE', (string)$item['item_id'])
                        $item = $itemSapService->getOdataClient()->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                        
                        if ($item) {
                            $response1 = $itemSapService->getOdataClient()->from('Items')
                                ->whereKey($item->ItemCode)
                                ->patch([
                                    'U_SH_ITEM_CODE' => $shItemCode
                                ]);

                            $shopeePriceUpdate = new ShopeeService('/product/update_price', 'shop', $accessResponseArr['access_token']);
                            $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
                                'item_id' => (int) $shItemId,
                                'price_list' => [
                                    [
                                        'model_id' => (int) $shItemCode,
                                        'original_price' => (int) $item['ItemPrices'][9]['Price']
                                    ]
                                ]
                            ]);

                            $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $accessResponseArr['access_token']);
                            $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
                                'item_id' => (int) $shItemId,
                                'stock_list' => [
                                    [
                                        'model_id' => (int) $shItemCode,
                                        'normal_stock' => (int) $item['QuantityOnStock']
                                    ]
                                ]
                            ]);

                            // dd($shopeePriceUpdateResponse);
                            // dd($shopeeStockUpdateResponse);
                        }
                    } catch(ClientException $e) {
                        dd($e->getResponse()->getBody()->getContents());
                    }
                }

                // dd('endif');
            } else {
                // dd('else');

                try {
                    $itemSapService = new SapService();
                        // ('U_SH_ITEM_CODE', (string)$item['item_id'])
                  
                    $item = $itemSapService->getOdataClient()->from('Items')
                        ->whereNested(function($query) use ($sku) {
                            $query->where('ItemCode', $sku)
                                ->orWhere('U_OLD_SKU', $sku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->first();
                    
                    // if ($key == 13) {
                    //     dd($item);
                    // }

                    if ($item) {
                        $response1 = $itemSapService->getOdataClient()->from('Items')
                            ->whereKey($item->ItemCode)
                            ->patch([
                                'U_SH_ITEM_CODE' => $shItemCode
                            ]);    
                            
                        $shopeePriceUpdate = new ShopeeService('/product/update_price', 'shop', $accessResponseArr['access_token']);
                        $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
                            'item_id' => (int) $shItemCode,
                            'price_list' => [
                                [
                                    'model_id' => 0,
                                    'original_price' => (int) $item['ItemPrices'][9]['Price']
                                ]
                            ]
                        ]);

                        $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $accessResponseArr['access_token']);
                        $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
                            'item_id' => (int) $shItemCode,
                            'stock_list' => [
                                [
                                    'model_id' => 0,
                                    'normal_stock' => (int) $item['QuantityOnStock']
                                ]
                            ]
                        ]);

                        // dd($shopeePriceUpdateResponse);
                        // dd($shopeeStockUpdateResponse);
                    }
                } catch(ClientException $e) {
                    dd($e->getResponse()->getBody()->getContents());
                }
            }
        }
    }
}
