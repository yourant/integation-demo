<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Services\SapService;
use Illuminate\Http\Request;
use App\Services\ShopeeService;
use Illuminate\Support\Facades\Http;

class ShopeeController extends Controller
{
    public function index()
    {
        return view('shopee.dashboard');
    }

    public function shopAuth()
    {
        $timestamp = time();
        $partnerId = 1000909;
        $partnerKey = 'e1b4853065602808a3647497ddde7568daa575c459de48a99b074d97bc9244d0';
        $path = '/api/v2/shop/auth_partner';
        $host = 'https://partner.test-stable.shopeemobile.com';
        $redirectUrl = route('shopee.init-token');

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
        
        $shopeePriceUpdate = new ShopeeService('/product/add_item', 'shop', $shopeeToken->access_token);
        $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
            'item_name' => "GAX 18V-30 Multi-Charger (10.8V - 18V)",
            'description' => "Dual bay charger takes 10.8V and 18V batteries.",
            'original_price' => 113,
            'normal_stock' => 15,
            'weight' => 3.0,
            'category_id' => 100482,
            'item_sku' => "101600A011AB",
            'brand' => [
                'brand_id' => 0
            ],
            'image' => [
                'image_id_list' => [
                    "c2f99915aa74f198efacf8577eae2ca6"
                ]
            ],
            'logistic_info' => [
                [
                    'shipping_fee' => 2.0,
                    'enabled' => true,
                    'logistic_id' => 10014
                ]
            ]
        ]);

        $shopeePriceUpdateResponseArr = json_decode($shopeePriceUpdateResponse->body(), true);
        // dd($shopeePriceUpdateResponseArr);

        $itemSapService = new SapService();

        $response1 = $itemSapService->getOdataClient()->from('Items')
            ->whereKey('101600A011AB')
            ->patch([
                'U_SH_ITEM_CODE' => $shopeePriceUpdateResponseArr['response']['item_id']
            ]); 

        // dd(json_decode($shopeePriceUpdateResponse->body(), true));

        $msg = "This is a simple message.";
        return response()->json(array('msg'=> $msg), 200);
    }

    public function updatePrice()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();

        $shopeePriceUpdate = new ShopeeService('/product/update_price', 'shop', $shopeeToken->access_token);
        $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
            'item_id' => (int) 100022158,
            'price_list' => [
                [
                    'model_id' => (int) 0,
                    'original_price' => (float) 113
                ]
            ]
        ]);
    }

    public function updateStock()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $shopeeToken->access_token);
        $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
            'item_id' => (int) 100022158,
            'stock_list' => [
                [
                    'model_id' => (int) 0,
                    'normal_stock' => (int) 15
                ]
            ]
        ]);
    } 

    public function generateSalesorder()
    {
        $salesOrderSapService = new SapService();

        $salesOrderList = [
            'CardCode' => 'Shopee_C',
            'NumAtCard' => '2106220W8P0PSK',
            'DocDate' => date('Y-m-d', 1624332509),
            'DocDueDate' => date('Y-m-d', 1624505315),
            'TaxDate' => date('Y-m-d', 1624332509),
            'U_Ecommerce_Type' => 'Shopee',
            'U_Order_ID' => '2106220W8P0PSK',
            'U_Customer_Name' => 'Paul Jao',
            'U_Customer_Phone' => '639457505051',
            'U_Customer_Shipping_Address' => 'Makiling Street, Bermuda, Pamplona Uno Las Pinas, Pamplona Uno, Las Pinas City, Metro Manila, Metro Manila, 1742',
            'DocumentLines' => [
                [
                    'ItemCode' => 'SH00002',
                    'Quantity' => 1,
                    'VatGroup' => 'SR',
                    'UnitPrice' => 1000 / 1.07
                ], [
                    'ItemCode' => 'TransportCharges',
                    'Quantity' => 1,
                    'VatGroup' => 'SR',
                    'UnitPrice' => 155 / 1.07
                ]
            ]
        ];

        $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);

        $salesOrderList = [
            'CardCode' => 'Shopee_C',
            'NumAtCard' => '2106221AMAAJ6X',
            'DocDate' => date('Y-m-d', 1624332509),
            'DocDueDate' => date('Y-m-d', 1624505315),
            'TaxDate' => date('Y-m-d', 1624332509),
            'U_Ecommerce_Type' => 'Shopee',
            'U_Order_ID' => '2106221AMAAJ6X',
            'U_Customer_Name' => 'Paul Jao',
            'U_Customer_Phone' => '639457505051',
            'U_Customer_Shipping_Address' => 'Makiling Street, Bermuda, Pamplona Uno Las Pinas, Pamplona Uno, Las Pinas City, Metro Manila, Metro Manila, 1742',
            'DocumentLines' => [
                [
                    'ItemCode' => 'SH00003',
                    'Quantity' => 1,
                    'VatGroup' => 'SR',
                    'UnitPrice' => 9999 / 1.07
                ], [
                    'ItemCode' => 'SH00002',
                    'Quantity' => 2,
                    'VatGroup' => 'SR',
                    'UnitPrice' => 1000 / 1.07
                ]
            ]
        ];

        $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);

        // $salesOrderList = [
        //     'CardCode' => 'Shopee_C',
        //     'NumAtCard' => '2106221FDBDPG4',
        //     'DocDate' => date('Y-m-d', 1624351992),
        //     'DocDueDate' => date('Y-m-d', 1624524805),
        //     'TaxDate' => date('Y-m-d', 1624351992),
        //     'U_Ecommerce_Type' => 'Shopee',
        //     'U_Order_ID' => '2106221FDBDPG4',
        //     'U_Customer_Name' => 'Paul Jao',
        //     'U_Customer_Phone' => '639457505051',
        //     'U_Customer_Shipping_Address' => 'Makiling Street, Bermuda, Pamplona Uno Las Pinas, Pamplona Uno, Las Pinas City, Metro Manila, Metro Manila, 1742',
        //     'DocumentLines' => [
        //         [
        //             'ItemCode' => 'SH00002',
        //             'Quantity' => 2,
        //             'VatGroup' => 'SR',
        //             'UnitPrice' => 1000 / 1.07
        //         ], [
        //             'ItemCode' => 'SH00001',
        //             'Quantity' => 1,
        //             'VatGroup' => 'SR',
        //             'UnitPrice' => 200 / 1.07
        //         ], [
        //             'ItemCode' => 'TransportCharges',
        //             'Quantity' => 1,
        //             'VatGroup' => 'SR',
        //             'UnitPrice' => 375 / 1.07
        //         ]
        //     ]
        // ];

        // $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);
    }

    public function generateInvoice()
    {
        $salesOrderSapService = new SapService();

        $salesOrderList = [
            'CardCode' => 'Shopee_C',
            'NumAtCard' => '2106221FDBDPG4',
            'DocDate' => date('Y-m-d', 1624351992),
            'DocDueDate' => date('Y-m-d', 1624524805),
            'TaxDate' => date('Y-m-d', 1624351992),
            'U_Ecommerce_Type' => 'Shopee',
            'U_Order_ID' => '2106221FDBDPG4',
            'U_Customer_Name' => 'Paul Jao',
            'U_Customer_Phone' => '639457505051',
            'U_Customer_Shipping_Address' => 'Makiling Street, Bermuda, Pamplona Uno Las Pinas, Pamplona Uno, Las Pinas City, Metro Manila, Metro Manila, 1742',
            'DocumentLines' => [
                [
                    'BaseType' => 17,
                    'BaseEntry' => 199,
                    'BaseLine' => 0
                ], [
                    'BaseType' => 17,
                    'BaseEntry' => 199,
                    'BaseLine' => 1
                ], [
                    'BaseType' => 17,
                    'BaseEntry' => 199,
                    'BaseLine' => 2
                ]
            ]
        ];

        $salesOrder = $salesOrderSapService->getOdataClient()->post('Invoices', $salesOrderList);
    }

    public function index2()
    {
        return view('lazada.dashboard');
    }
}
