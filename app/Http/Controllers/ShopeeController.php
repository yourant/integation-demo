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
        // dd($shopeePriceUpdateResponseArr['response']['item_id']);

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
            'item_id' => (int) 129541,
            'price_list' => [
                [
                    'model_id' => (int) 0,
                    'original_price' => (float) 30
                ]
            ]
        ]);

        $shopeePriceUpdate = new ShopeeService('/product/update_price', 'shop', $shopeeToken->access_token);
        $shopeePriceUpdateResponse = Http::post($shopeePriceUpdate->getFullPath() . $shopeePriceUpdate->getShopQueryString(), [
            'item_id' => (int) 129531,
            'price_list' => [
                [
                    'model_id' => (int) 0,
                    'original_price' => (float) 50
                ]
            ]
        ]);
    }

    public function updateStock()
    {
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        
        $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $shopeeToken->access_token);
        $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
            'item_id' => (int) 129541,
            'stock_list' => [
                [
                    'model_id' => (int) 0,
                    'normal_stock' => (int) 10
                ]
            ]
        ]);

        $shopeeStockUpdate = new ShopeeService('/product/update_stock', 'shop', $shopeeToken->access_token);
        $shopeeStockUpdateResponse = Http::post($shopeeStockUpdate->getFullPath() . $shopeeStockUpdate->getShopQueryString(), [
            'item_id' => (int) 129531,
            'stock_list' => [
                [
                    'model_id' => (int) 0,
                    'normal_stock' => (int) 50
                ]
            ]
        ]);
    }

    public function index2()
    {
        return view('lazada.dashboard');
    }  
    
    public function syncProduct()
    {

    }

    public function updatePrice()
    {

    }

    public function updateStock()
    {

    }

    public function generateSalesorder()
    {

    }

    public function generateInvoice()
    {

    }

}
