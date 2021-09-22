<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Services\SapService;
use Illuminate\Http\Request;
use App\Services\ShopeeService;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;

class ShopeeController extends Controller
{
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

        if ($updatedToken) {
            dd('Successfully authorize shop');
        } else {
            dd('Failed to authorized shop');
        }
    }

    public function syncItem()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();   
        
        $productList = [];
        $moreProducts = true;
        $offset = 0;
        $pageSize = 50;

        // retrieve products with base
        while ($moreProducts) {
            $productSegmentList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeToken->access_token);
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'NORMAL',
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = json_decode($shopeeProductsResponse->body(), true);

            foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                array_push($productSegmentList, $item['item_id']);
            }

            $productStr = implode(",", $productSegmentList);
            // for testing
            // $productStr = '5392771665,8070898047,1199243276';
            
            $shopeeProductBase = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeToken->access_token);
            $shopeeProductBaseResponse = Http::get($shopeeProductBase->getFullPath(), array_merge([
                'item_id_list' => $productStr
            ], $shopeeProductBase->getShopCommonParameter()));
            
            $shopeeProductBaseResponseArr = json_decode($shopeeProductBaseResponse->body(), true);

            $productList = array_merge($productList, $shopeeProductBaseResponseArr['response']['item_list']);
            // for testing
            // $productList = $shopeeProductBaseResponseArr['response']['item_list'];

            if ($shopeeProductsResponseArr['response']['has_next_page']) {
                $offset += $pageSize;
            } else {
                $moreProducts = false;
            }   
        }

        foreach ($productList as $product) {
            $parentSku = $product['item_sku'];
            $productId = $product['item_id'];

            $itemSapService = new SapService();

            if ($product['has_model']) {
                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = json_decode($shopeeModelsResponse->body(), true);

                foreach ($shopeeModelsResponseArr['response']['model'] as $key => $model) {                                
                    $sku = $model['model_sku'];

                    try {
                        $item = $itemSapService->getOdataClient()->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                        
                        if ($item) {
                            $itemUpdateResponse = $itemSapService->getOdataClient()->from('Items')
                                ->whereKey($item->ItemCode)
                                ->patch([
                                    'U_SH_ITEM_CODE' => $productId
                                ]);
                        }
                    } catch(ClientException $e) {
                        dd($e->getResponse()->getBody()->getContents());
                    }
                }
            } else {
                try {
                    $item = $itemSapService->getOdataClient()->from('Items')
                        ->whereNested(function($query) use ($parentSku) {
                            $query->where('ItemCode', $parentSku)
                                ->orWhere('U_OLD_SKU', $parentSku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->first();

                    if ($item) {
                        $itemUpdateResponse = $itemSapService->getOdataClient()->from('Items')
                            ->whereKey($item->ItemCode)
                            ->patch([
                                'U_SH_ITEM_CODE' => $productId
                            ]);
                    }
                } catch(ClientException $e) {
                    dd($e->getResponse()->getBody()->getContents());
                }
            }
        }

        return response()->json(null, 200);
    }

    public function updatePrice()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();   
        
        $productList = [];
        $moreProducts = true;
        $offset = 0;
        $pageSize = 50;

        // retrieve products with base
        while ($moreProducts) {
            $productSegmentList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeToken->access_token);
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'NORMAL',
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = json_decode($shopeeProductsResponse->body(), true);

            foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                array_push($productSegmentList, $item['item_id']);
            }

            $productStr = implode(",", $productSegmentList);
            // for test
            // $productStr = '5392771665,8070898047';
            
            $shopeeProductBase = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeToken->access_token);
            $shopeeProductBaseResponse = Http::get($shopeeProductBase->getFullPath(), array_merge([
                'item_id_list' => $productStr
            ], $shopeeProductBase->getShopCommonParameter()));
            
            $shopeeProductBaseResponseArr = json_decode($shopeeProductBaseResponse->body(), true);

            $productList = array_merge($productList, $shopeeProductBaseResponseArr['response']['item_list']);
            // for test
            // $productList = $shopeeProductBaseResponseArr['response']['item_list'];

            if ($shopeeProductsResponseArr['response']['has_next_page']) {
                $offset += $pageSize;
            } else {
                $moreProducts = false;
            }   
        }

        foreach ($productList as $product) {
            $parentSku = $product['item_sku'];
            $productId = $product['item_id'];

            $itemSapService = new SapService();

            if ($product['has_model']) {
                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = json_decode($shopeeModelsResponse->body(), true);

                foreach ($shopeeModelsResponseArr['response']['model'] as $key => $model) {                                
                    $modelId = $model['model_id'];
                    $sku = $model['model_sku'];

                    try {
                        $item = $itemSapService->getOdataClient()->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                        
                        if ($item) {
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
                        }
                    } catch(ClientException $e) {
                        dd($e->getResponse()->getBody()->getContents());
                    }
                }
            } else {
                try {
                    $item = $itemSapService->getOdataClient()->from('Items')
                        ->whereNested(function($query) use ($parentSku) {
                            $query->where('ItemCode', $parentSku)
                                ->orWhere('U_OLD_SKU', $parentSku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->first();

                    if ($item) {
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
                    }
                } catch(ClientException $e) {
                    dd($e->getResponse()->getBody()->getContents());
                }
            }
        }

        return response()->json(null, 200);
    }

    public function updateStock()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();   
        
        $productList = [];
        $moreProducts = true;
        $offset = 0;
        $pageSize = 50;

        // retrieve products with base
        while ($moreProducts) {
            $productSegmentList = [];

            $shopeeProducts = new ShopeeService('/product/get_item_list', 'shop', $shopeeToken->access_token);
            $shopeeProductsResponse = Http::get($shopeeProducts->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'offset' => $offset,
                'item_status' => 'NORMAL',
            ], $shopeeProducts->getShopCommonParameter()));

            $shopeeProductsResponseArr = json_decode($shopeeProductsResponse->body(), true);

            foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                array_push($productSegmentList, $item['item_id']);
            }

            $productStr = implode(",", $productSegmentList);
            // for test
            // $productStr = '5392771665,8070898047';
            
            $shopeeProductBase = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeToken->access_token);
            $shopeeProductBaseResponse = Http::get($shopeeProductBase->getFullPath(), array_merge([
                'item_id_list' => $productStr
            ], $shopeeProductBase->getShopCommonParameter()));
            
            $shopeeProductBaseResponseArr = json_decode($shopeeProductBaseResponse->body(), true);

            $productList = array_merge($productList, $shopeeProductBaseResponseArr['response']['item_list']);
            // for test
            // $productList = $shopeeProductBaseResponseArr['response']['item_list'];

            if ($shopeeProductsResponseArr['response']['has_next_page']) {
                $offset += $pageSize;
            } else {
                $moreProducts = false;
            }   
        }

        foreach ($productList as $product) {
            $parentSku = $product['item_sku'];
            $productId = $product['item_id'];

            $itemSapService = new SapService();

            if ($product['has_model']) {
                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeToken->access_token);
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                $shopeeModelsResponseArr = json_decode($shopeeModelsResponse->body(), true);

                foreach ($shopeeModelsResponseArr['response']['model'] as $key => $model) {                                
                    $modelId = $model['model_id'];
                    $sku = $model['model_sku'];

                    try {
                        $item = $itemSapService->getOdataClient()->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                        
                        if ($item) {
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
                        }
                    } catch(ClientException $e) {
                        dd($e->getResponse()->getBody()->getContents());
                    }
                }
            } else {
                try {
                    $item = $itemSapService->getOdataClient()->from('Items')
                        ->whereNested(function($query) use ($parentSku) {
                            $query->where('ItemCode', $parentSku)
                                ->orWhere('U_OLD_SKU', $parentSku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->first();

                    if ($item) {
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
                    }
                } catch(ClientException $e) {
                    dd($e->getResponse()->getBody()->getContents());
                }
            }
        }

        return response()->json(null, 200);
    } 

    public function generateSalesorder()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        $orderList = [];
        $moreReadyOrders = true;
        $offset = 0;
        $pageSize = 50;
        
        while ($moreReadyOrders) {
            $shopeeReadyOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeToken->access_token);
            $shopeeReadyOrdersResponse = Http::get($shopeeReadyOrders->getFullPath(), array_merge([
                'time_range_field' => 'update_time',
                'time_from' => strtotime(date("Y-m-d 00:00:00")),
                'time_to' => strtotime(date("Y-m-d 23:59:59")),
                'page_size' => $pageSize,
                'cursor' => $offset,
                'order_status' => 'READY_TO_SHIP',
                'response_optional_fields' => 'order_status'
            ], $shopeeReadyOrders->getShopCommonParameter()));

            $shopeeReadyOrdersResponseArr = json_decode($shopeeReadyOrdersResponse->body(), true);

            foreach ($shopeeReadyOrdersResponseArr['response']['order_list'] as $order) {
                array_push($orderList, $order['order_sn']);
            }

            if ($shopeeReadyOrdersResponseArr['response']['more']) {
                $offset += $pageSize;
            } else {
                $moreReadyOrders = false;
            }   
        }
        // dd($orderList);
        $orderStr = implode(",", $orderList);
        // for testing
        // $orderStr = '18033000113B04H';
        
        $shopeeOrderDetail = new ShopeeService('/order/get_order_detail', 'shop', $shopeeToken->access_token);
        $shopeeOrderDetailResponse = Http::get($shopeeOrderDetail->getFullPath(), array_merge([
            'order_sn_list' => $orderStr,
            'response_optional_fields' => 'total_amount,item_list,buyer_user_id,buyer_username,recipient_address,estimated_shipping_fee,actual_shipping_fee,actual_shipping_fee_confirmed'
        ], $shopeeOrderDetail->getShopCommonParameter()));

        $shopeeOrderDetailResponseArr = json_decode($shopeeOrderDetailResponse->body(), true);
        $orderListDetails = $shopeeOrderDetailResponseArr['response']['order_list'];

        $salesOrderSapService = new SapService();

        $salesOrderList = [];
        $ecm = $salesOrderSapService->getOdataClient()->from('U_ECM')->get();

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
        
        foreach ($orderListDetails as $order) {
            $existedSO = $salesOrderSapService->getOdataClient()
                ->select('DocNum')
                ->from('Orders')
                ->where('U_Order_ID', (string)$order['order_sn'])
                ->where('CancelStatus', 'csNo')
                ->first();

            if (!$existedSO) {
                $escrowDetail = new ShopeeService('/payment/get_escrow_detail', 'shop', $shopeeToken->access_token);
                $escrowDetailResponse = Http::get($escrowDetail->getFullPath(), array_merge([
                    'order_sn' => $order['order_sn']
                ], $escrowDetail->getShopCommonParameter()));
                $escrowDetailResponseArr = json_decode($escrowDetailResponse->body(), true);
                $escrow = $escrowDetailResponseArr['response'];

                $itemList = [];

                foreach ($order['item_list'] as $item) {                   
                    try {
                        $response = $salesOrderSapService->getOdataClient()
                            ->from('Items')
                            ->where('U_SH_ITEM_CODE', (string)$item['item_id'])
                            ->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                    } catch(ClientException $e) {
                        dd($e->getResponse()->getBody()->getContents());
                    }

                    $sapItem = $response['properties'];

                    $itemList[] = [
                        'ItemCode' => $sapItem['ItemCode'],
                        'Quantity' => $item['model_quantity_purchased'],
                        'VatGroup' => $taxCode,
                        'UnitPrice' => $item['model_discounted_price'] / $taxPercentage
                    ];
                }

                if ($escrow['order_income']['buyer_paid_shipping_fee']) {
                    $itemList[] = [
                        'ItemCode' => $shippingItem,
                        'Quantity' => 1,
                        'VatGroup' => $taxCode,
                        'UnitPrice' => $escrow['order_income']['buyer_paid_shipping_fee'] / $taxPercentage
                    ];           
                }

                if ($escrow['order_income']['voucher_from_shopee']) {
                    $itemList[] = [
                        'ItemCode' => $shopVoucherItem,
                        'Quantity' => -1,
                        'VatGroup' => $taxCode,
                        'UnitPrice' => $escrow['order_income']['voucher_from_shopee'] / $taxPercentage
                    ];           
                }

                if ($escrow['order_income']['voucher_from_seller']) {
                    $itemList[] = [
                        'ItemCode' => $sellerVoucherItem,
                        'Quantity' => -1,
                        'VatGroup' => $taxCode,
                        'UnitPrice' => $escrow['order_income']['voucher_from_seller'] / $taxPercentage
                    ];           
                }
                
                if ($escrow['order_income']['coins']) {
                    $itemList[] = [
                        'ItemCode' => $shopeeCoinItem,
                        'Quantity' => -1,
                        'VatGroup' => $taxCode,
                        'UnitPrice' => $escrow['order_income']['coins'] / $taxPercentage
                    ];           
                }

                $salesOrderList = [
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
                ];

                $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);

                if ($salesOrder) {
                    return response()->json(null, 200);
                } else {
                    return response()->json(null, 500);
                }
            }        
        }
    }

    public function generateInvoice()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        $orderList = [];
        $moreShippedOrders = true;
        $offset = 0;
        $pageSize = 50;
        
        while ($moreShippedOrders) {
            $shopeeShippedOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeToken->access_token);
            $shopeeShippedOrdersResponse = Http::get($shopeeShippedOrders->getFullPath(), array_merge([
                'time_range_field' => 'update_time',
                'time_from' => strtotime(date("Y-m-d 00:00:00")),
                'time_to' => strtotime(date("Y-m-d 23:59:59")),
                // 'time_from' => 1627833600,
                // 'time_to' => 1627919999,
                'page_size' => $pageSize,
                'cursor' => $offset,
                'order_status' => 'SHIPPED',
                'response_optional_fields' => 'order_status'
            ], $shopeeShippedOrders->getShopCommonParameter()));

            $shopeeShippedOrdersResponseArr = json_decode($shopeeShippedOrdersResponse->body(), true);

            foreach ($shopeeShippedOrdersResponseArr['response']['order_list'] as $order) {
                array_push($orderList, $order['order_sn']);
            }

            if ($shopeeShippedOrdersResponseArr['response']['more']) {
                $offset += $pageSize;
            } else {
                $moreShippedOrders = false;
            }   
        }

        // for testing
        // $orderList = ['210803K1WFX89R'];

        $invoiceSapService = new SapService();
        $invoiceList = [];

        foreach ($orderList as $order) {

            $salesOrder = $invoiceSapService->getOdataClient()
                ->from('Orders')
                ->where('U_Order_ID', (string)$order)
                ->where('DocumentStatus', 'bost_Open')
                ->where('CancelStatus', 'csNo')
                ->first();

            $existInv = $invoiceSapService->getOdataClient()
                ->from('Invoices')
                ->where('U_Order_ID', (string)$order)
                ->first();

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

                $invoiceList = [
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
                ];

                $invoice = $invoiceSapService->getOdataClient()->post('Invoices', $invoiceList);

                if ($invoice) {
                    return response()->json(null, 200);
                } else {
                    return response()->json(null, 500);
                }
            }
        }
    }

    public function generateCreditmemo()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        $returnList = [];
        $moreReturnItem = true;
        $offset = 0;
        $pageSize = 50;

        while ($moreReturnItem) {
            $shopeeReturnItems = new ShopeeService('/returns/get_return_list', 'shop', $shopeeToken->access_token);
            $shopeeReturnItemsResponse = Http::get($shopeeReturnItems->getFullPath(), array_merge([
                'page_size' => $pageSize,
                'page_no' => $offset
            ], $shopeeReturnItems->getShopCommonParameter()));

            $shopeeReturnItemsResponseArr = json_decode($shopeeReturnItemsResponse->body(), true);

            foreach ($shopeeReturnItemsResponseArr['response']['return'] as $return) {
                array_push($returnList, $return);
            }

            if ($shopeeReturnItemsResponseArr['response']['more']) {
                $offset += $pageSize;
            } else {
                $moreReturnItem = false;
            }
        }

        // for testing
        // foreach ($returnList as $value) {
        //     if ($value['return_sn'] == '200124144731506') {
        //     // if ($value['return_sn'] == '190923173135204') {
        //         $returnList = [];
        //         array_push($returnList, $value);
        //     }
        // }

        $itemList = [];
        $returnSapService = new SapService();

        $ecm = $returnSapService->getOdataClient()->from('U_ECM')->get();

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

        // dd($returnList);
        foreach ($returnList as $returnItem) {
            $order = $returnSapService->getOdataClient()
                ->from('Orders')
                ->where('U_Order_ID', (string)$returnItem['order_sn'])
                ->where('CancelStatus', 'csNo')
                ->first();

            if ($order && $returnItem['status'] == 'REFUND_PAID') {
                foreach ($returnItem['item'] as $item) {
                    try {
                        $response = $returnSapService->getOdataClient()
                            ->from('Items')
                            ->where('U_SH_ITEM_CODE', (string)$item['item_id'])
                            ->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                    } catch(ClientException $e) {
                        dd($e->getResponse()->getBody()->getContents());
                    }

                    $sapItem = $response['properties'];

                    $itemList[] = [
                        'ItemCode' => $sapItem['ItemCode'],
                        'Quantity' => $item['amount'],
                        'VatGroup' => $taxCode,
                        'UnitPrice' => $item['item_price'] / $taxPercentage
                    ];
                }

                $escrowDetail = new ShopeeService('/payment/get_escrow_detail', 'shop', $shopeeToken->access_token);
                $escrowDetailResponse = Http::get($escrowDetail->getFullPath(), array_merge([
                    'order_sn' => $returnItem['order_sn']
                ], $escrowDetail->getShopCommonParameter()));
                $escrowDetailResponseArr = json_decode($escrowDetailResponse->body(), true);
                $escrow = $escrowDetailResponseArr['response']['order_income'];
                // dd($escrow);
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

                $creditMemoList = [
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
                ];
                // dd($creditMemoList);
                $creditMemo = $returnSapService->getOdataClient()->post('CreditNotes', $creditMemoList);

                if ($creditMemo) {
                    return response()->json(null, 200);
                } else {
                    return response()->json(null, 500);
                }
            }
        }
    }

    public function index2()
    {
        return view('lazada.dashboard');
    }
}