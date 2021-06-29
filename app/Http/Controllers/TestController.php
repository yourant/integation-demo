<?php

namespace App\Http\Controllers;

use App\Services\SapService;
use App\Services\ShopeeService;
use Exception;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\TransferStats;
use App\Traits\SapConnectionTrait;
use SaintSystems\OData\ODataClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;
use GuzzleHttp\Exception\ClientException;

class TestController extends Controller
{
    use SapConnectionTrait;
    
    public function form(Request $request)
    {
        // dd(session('B1SESSION'));
        // dd($request->all());
        // dd('test');
        // dd(''); 
        return view('test.form');
    }

    public function login(Request $request)
    {
        // $cookieFile = 'cookie_jar.txt';
        // $cookies = new CookieJar();

        // $odataServiceUrl = 'https://192.168.18.160:50000/b1s/v1';

        // $odataClient = new ODataClient($odataServiceUrl, function($request) {
        //     // OAuth Bearer Token Authentication
        //     // $request->headers['Authorization'] = 'Bearer '.$accessToken;
        //     // $request->options['verify'] = false;
        //     // dd($request);
        //     // // OR Basic Authentication
        //     // $username = 'foo';
        //     // $password = 'bar';
        //     // $request->headers['Authorization'] = 'Basic '.base64_encode($username.':'.$password);
        //     // $request->headers['Authorization']
        // });
        // // dd($odataClient);

        // $httpProvider = $odataClient->getHttpProvider();
        // $httpProvider->setExtraOptions([
        //     'verify' => false,
        //     'cookies' => $cookies
        // ]);

        // $login = $odataClient->post('Login', [
        //     'CompanyDB' => $request->input('db'),
        //     'UserName' => $request->input('uname'),
        //     'Password' => $request->input('pword')
        // ]);
        //     // dd($cookies->getCookieByName('B1SESSION')['']);
        // // $cookies->shouldPersist($cookies->getCookieByName('B1SESSION'), true);
        // session([
        //     'B1SESSION' => $cookies->getCookieByName('B1SESSION'), 
        //     'ROUTEID' => $cookies->getCookieByName('ROUTEID')
        // ]);

        // dd(Cookie::get('B1SESSION'));
        // $cookies->shouldPersist(new SetCookie($cookies->toArray()), true);
        // dd($login);

        $login = $this->initConnection($request);
        
        return view('test.index', compact('login'));
    }

    public function login2(Request $request)
    {
        $odataServiceUrl = 'https://192.168.18.160:50000/b1s/v1';

        $odataClient = new ODataClient($odataServiceUrl, function($request) {
            $request->headers['Cookie'] = session('B1SESSION') . '; ' . session('ROUTEID');
        });

        $httpProvider = $odataClient->getHttpProvider();
        $httpProvider->setExtraOptions([
            'verify' => false
        ]);
 
        // $httpProvider->configureDefaults(['cookies' => true]);

        // dd($httpProvider);
        // $httpProvider->setExtraOptions(['verify' => false]);
        

        // $newPerson = $odataClient->post('Login', [
        //     'CompanyDB' => 'SBODEMOSG',
        //     'UserName' => 'manager',
        //     'Password' => 'manager'
        // ]);  
        //    dd($httpProvider);  
        $person = $odataClient->from('BusinessPartners')->find('0716');
        $person = $person['properties'];
        dd($person);
        // dd($person);
        // dd($cookies); 
        
        // $cookies->shouldPersist(new SetCookie($cookies->toArray()), true);
        // $hmmm = $cookies->getCookieByName('B1SESSION');
        // dd($hmmm->toArray());

        // $httpProvider->setExtraOptions([
        //     'cookies' => $cookies
        // ]);

        return view('test.index', compact('person'));
    }

    public function index()
    {        
        // init
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

            foreach ($shopeeProductsResponseArr['response']['item'] as $item) {
                array_push($productSegmentList, $item['item_id']);
            }

            $productStr = implode(",", $productSegmentList);
            
            $shopeeProductBase = new ShopeeService('/product/get_item_base_info', 'shop', $shopeeAccess->getAccessToken());
            $shopeeProductBaseResponse = Http::get($shopeeProductBase->getFullPath(), array_merge([
                'item_id_list' => $productStr
            ], $shopeeProductBase->getShopCommonParameter()));

            $shopeeProductBaseResponseArr = json_decode($shopeeProductBaseResponse->body(), true);
            $productList = array_merge($productList, $shopeeProductBaseResponseArr['response']['item_list']);

            if ($shopeeProductsResponseArr['response']['has_next_page']) {
                $offset += $pageSize;
            } else {
                $moreProducts = false;
            }   
        }

        // dadas
        
        
        foreach ($productList as $product) {
            // dd($product);
            // $sku = [];

            if ($product['has_model']) {
                // dd('dasdasdas');
                $shopeeModels = new ShopeeService('/product/get_model_list', 'shop', $shopeeAccess->getAccessToken());
                $shopeeModelsResponse = Http::get($shopeeModels->getFullPath(), array_merge([
                    'item_id' => $product['item_id']
                ], $shopeeModels->getShopCommonParameter()));

                // dd('ttt');
                $shopeeModelsResponseArr = json_decode($shopeeModelsResponse->body(), true);
                // dd($shopeeModelsResponseArr['response']['model']);
                

                foreach ($shopeeModelsResponseArr['response']['model'] as $model) {
                    // dd($model['model_sku']);
                    // $sku[] = $model['model_sku'];
                    // $model[] = $model['model_id'];
                    $sku = $model['model_sku'];
                    $itemCode = $model['model_id'];

                   

                    try {
                        $itemSapService = new SapService();
                        // ('U_SH_ITEM_CODE', (string)$item['item_id'])
                        $item = $itemSapService->getOdataClient()->from('Items')
                            ->whereNested(function($query) use ($sku) {
                                $query->where('ItemCode', $sku)
                                    ->orWhere('U_OLD_SKU', $sku);
                            })->where('U_SH_INTEGRATION', 'Yes')
                            ->first();
                        $tes = $item['properties']['ItemCode'];
                        // dd($tes);
                        
                        $itemSapService2 = new SapService();
                        $response1 = $itemSapService2->getOdataClient()->from('Items')
                            ->whereKey($tes)
                            ->patch(['U_SH_ITEM_CODE' => $itemCode]);

                        // dd($response1);


                            // ->patch(['U_SH_ITEM_CODE' => $itemCode]);

                    } catch(ClientException $e) {
                        dd($e->getResponse()->getBody()->getContents());
                    }
                    // dd()
                    // dd($response[0]['properties']);
                    // $sapItem = $response[0]['properties'];
                }
            } else {
                dd('else');
                $sku = $product['item_sku'];
                $itemCode = $product['item_id'];

                try {
                    // ('U_SH_ITEM_CODE', (string)$item['item_id'])
                    $response = $itemSapService->getOdataClient()->from('Items')
                        ->where(function($query) use ($sku) {
                            $query->where('ItemCode', $sku)
                                ->orWhere('U_OLD_SKU', $sku);
                        })->where('U_SH_INTEGRATION', 'Yes')
                        ->patch(['U_SH_ITEM_CODE' => $itemCode]);

                    // $response = $itemSapService->getOdataClient()->patch("Items("."'".$sku."'".")", [
                    //     'U_SH_ITEM_CODE' => $lazadaItemId
                    // ]);
                } catch(ClientException $e) {
                    dd($e->getResponse()->getBody()->getContents());
                }
                // $itemSapService = new SapService();

                // try {
                //     // ('U_SH_ITEM_CODE', (string)$item['item_id'])
                //     $response = $itemSapService->getOdataClient()->from('Items')
                //         ->whereKey
                //         ->where(function($query) use ($sku) {
                //             $query->where('ItemCode', $sku)
                //                 ->orWhere('U_OLD_SKU', $sku);
                //         })->where('U_SH_INTEGRATION', 'Yes')
                //         ->patch(['U_SH_ITEM_CODE' => $product['item_id']]);

                //     // $response = $itemSapService->getOdataClient()->patch("Items("."'".$sku."'".")", [
                //     //     'U_SH_ITEM_CODE' => $lazadaItemId
                //     // ]);
                // } catch(ClientException $e) {
                //     dd($e->getResponse()->getBody()->getContents());
                // }
            }

            // $itemSapService = new SapService();

            // try {
            //     // ('U_SH_ITEM_CODE', (string)$item['item_id'])
            //     $response = $itemSapService->getOdataClient()->from('Items')
            //         ->where(function($query) use ($sku) {
            //             $query->whereIn('ItemCode', $sku)
            //                 ->orWhereIn('U_OLD_SKU', $sku);
            //         })->where('U_SH_INTEGRATION', 'Yes')
            //         ->patch(['U_SH_ITEM_CODE' => $model['model_id']]);

            //     // $response = $itemSapService->getOdataClient()->patch("Items("."'".$sku."'".")", [
            //     //     'U_SH_ITEM_CODE' => $lazadaItemId
            //     // ]);
            // } catch(ClientException $e) {
            //     dd($e->getResponse()->getBody()->getContents());
            // }
            
        }

        // dd($productList);
        dd('bound');




        $shopeeAccess = new ShopeeService('/auth/token/get', 'public');

        $accessResponse = Http::post($shopeeAccess->getFullPath() . $shopeeAccess->getAccessTokenQueryString(), [
            'code' => $shopeeAccess->getCode(),
            'partner_id' => $shopeeAccess->getPartnerId(),
            'shop_id' => $shopeeAccess->getShopId()
        ]);
        
        $accessResponseArr = json_decode($accessResponse->body(), true);
        $shopeeAccess->setAccessToken($accessResponseArr['access_token']);
        
        $orderList = [];
        $moreReadyOrders = true;
        $offset = 0;
        $pageSize = 50;
        
        while ($moreReadyOrders) {
            $shopeeReadyOrders = new ShopeeService('/order/get_order_list', 'shop', $shopeeAccess->getAccessToken());
            $shopeeReadyOrdersResponse = Http::get($shopeeReadyOrders->getFullPath(), array_merge([
                'time_range_field' => 'create_time',
                'time_from' => 1623970808,
                'time_to' => 1624575608,
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
        // dd($orderStr);
        
        $shopeeOrderDetail = new ShopeeService('/order/get_order_detail', 'shop', $shopeeAccess->getAccessToken());
        $shopeeOrderDetailResponse = Http::get($shopeeOrderDetail->getFullPath(), array_merge([
            'order_sn_list' => $orderStr,
            'response_optional_fields' => 'total_amount,item_list,buyer_user_id,buyer_username,recipient_address,estimated_shipping_fee,actual_shipping_fee,actual_shipping_fee_confirmed'
        ], $shopeeOrderDetail->getShopCommonParameter()));

        $shopeeOrderDetailResponseArr = json_decode($shopeeOrderDetailResponse->body(), true);
        $orderListDetails = $shopeeOrderDetailResponseArr['response']['order_list'];

        // dd($shopeeOrderDetailResponseArr['response']['order_list']);

        $salesOrderSapService = new SapService();
        $salesOrderList = [];

        foreach ($orderListDetails as $order) {
            // dd(date('Y-m-d', $order['ship_by_date']));
            // dd($order);
            $itemList = [];

            foreach ($order['item_list'] as $item) {
                
                // dd(is_string($item['item_id']));
                try {
                    $response = $salesOrderSapService->getOdataClient()->from('Items')->where('U_SH_ITEM_CODE', (string)$item['item_id'])->get();
                } catch(ClientException $e) {
                    dd($e->getResponse()->getBody()->getContents());
                }
                // dd($response[0]['properties']);
                $sapItem = $response[0]['properties'];

                $itemList[] = [
                    'ItemCode' => $sapItem['ItemCode'],
                    'Quantity' => $item['model_quantity_purchased'],
                    'TaxCode' => 'T1',
                    'UnitPrice' => $item['model_discounted_price']
                ];
            }
            // dd('hmmmm');
            $salesOrderList = [
                'CardCode' => 'Shopee_C',
                'DocDate' => date('Y-m-d', $order['create_time']),
                'DocDueDate' => date('Y-m-d', $order['ship_by_date']),
                'TaxDate' => date('Y-m-d', $order['create_time']),
                'DocTotal' => $order['total_amount'],
                'U_Ecommerce_Type' => 'Shopee',
                'U_Order_ID' => $order['order_sn'],
                'U_Customer_Name' => $order['buyer_username'],
                'U_Customer_Shipping_Address' => $order['recipient_address']['full_address'],
                'DocumentLines' => $itemList
            ];

            $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);
            // try {
            //     $salesOrder = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);
            // } catch(ClientException $e) {
            //     dd($e->getResponse()->getBody()->getContents());
            // }
            
            // dd($salesOrder);
            // dd($salesOrderList);
        }
        dd('test');
        // $sapItems = $salesOrderSapService->getOdataClient()->post('Orders', $salesOrderList);
        // dd($sapItems);
        // {
        //     "CardCode": "c001",
        //     "DocDueDate": "2014-04-04",
        //     "DocumentLines": [
        //         {
        //             "ItemCode": "i001",
        //             "Quantity": "100",
        //             "TaxCode": "T1",
        //             "UnitPrice": "30"
        //         }
        //     ]
        // }


        //add new SO
        


        $timestamp = time();
        $partnerId = 1000909;
        $partnerKey = 'e1b4853065602808a3647497ddde7568daa575c459de48a99b074d97bc9244d0';
        $path = '/api/v2/shop/auth_partner';
        $host = 'https://partner.test-stable.shopeemobile.com';
        $redirectUrl = config('app.url') . 'test2';

        $baseString = $partnerId . $path . $timestamp;
        // $fBaseString = utf8_decode($baseString);
        // $ffBaseString = 'b\'' . $baseString . '\'';
        // $ffPartnerKey = 'b\'' . $partnerKey . '\'';
        // dd('b\'' . $baseString . '\'');
        $sign = hash_hmac("sha256", $baseString, $partnerKey);
        // dd($sign . ' ' . $timestamp);

        $tokenPath = '/api/v2/auth/token/get';

        $baseString2 = $partnerId . $tokenPath . $timestamp;
        $sign2 = hash_hmac("sha256", $baseString2, $partnerKey);

        $code = '4257bc889237a08fb3fc8550e1fbdffd';
        $shopId = 10805;

        
        // $client = new Client();
        // $promise = $client->requestAsync('GET', $host . $path, [
        //     'query' => [
        //         'partner_id' => $partnerId,
        //         'timestamp' => $timestamp,
        //         'sign' => $sign,
        //         'redirect' => $redirectUrl
        //     ]
        // ]);
        // $response = $promise->wait();
        // dd($response->getBody());
        // $promise->then(function ($response) {
        //     dd($response);
        //     echo 'Got a response! ' . $response->getStatusCode();
        // });

        
        // authenticate
        // $response = Http::withOptions([
        //     'verify' => false,
        // ])->post('https://192.168.18.140:50000/b1s/v1/Login', [
        //     'CompanyDB' => 'TC_DEV',
        //     'Password' => '4021',
        //     'UserName' => 'kass'
        // ]);
        // // dd( $response->header('Set-Cookie'));
        // $odataClient = new ODataClient(config('app.sap_path'), function($request) use($response) {
        //     //set the header Set-cookie (from the authentication route) as cookie in the next request
        //     $request->headers['Cookie'] = $response->header('Set-Cookie');
        // });

        // $httpProvider = $odataClient->getHttpProvider();
        // $httpProvider->setExtraOptions([
        //     'verify' => false
        // ]);



        $shopeeAccess = new ShopeeService('/auth/token/get', 'public');
        
        $accessResponse = Http::post($shopeeAccess->getFullPath() . $shopeeAccess->getAccessTokenQueryString(), [
            'code' => $shopeeAccess->getCode(),
            'partner_id' => $shopeeAccess->getPartnerId(),
            'shop_id' => $shopeeAccess->getShopId()
        ]);
        
        $accessResponseArr = json_decode($accessResponse->body(), true);
        // dd($accessResponseArr);
        
      
        
        $shopeeShopInfo = new ShopeeService('/shop/get_shop_info', 'shop', $accessResponseArr['access_token']);

        $shopInfoResponse = Http::get($shopeeShopInfo->getFullPath(), [
            'partner_id' => $shopeeShopInfo->getPartnerId(),
            'timestamp' => $shopeeShopInfo->getTimestamp(),
            'access_token' => $shopeeShopInfo->getAccessToken(),
            'shop_id' => $shopeeShopInfo->getShopId(),
            'sign' => $shopeeShopInfo->getSign()
        ]);

        dd($shopInfoResponse->body());




        $tokenAccessUrl = 'https://partner.test-stable.shopeemobile.com/api/v2/auth/token/get';
        $query = '?sign=' . $sign2 . '&partner_id=' . $partnerId . '&timestamp=' . (string) $timestamp;

        $response3 = Http::post($tokenAccessUrl . $query, [
                'code' => $code,
                'partner_id' => $partnerId,
                'shop_id' => $shopId
        ]);
        // dd();
        $hmmmmmm = $response3->body();
        $getTokenResponse = json_decode($hmmmmmm, true);
        // dd($getTokenResponse);


        $authHost = 'https://partner.test-stable.shopeemobile.com';
        $authPath = '/api/v2/shop/get_shop_info';

        $baseString3 = $partnerId . $authPath . $timestamp . $getTokenResponse['access_token'] . $shopId;
        $sign3 = hash_hmac("sha256", $baseString3, $partnerKey);
        // $authUrl = 'https://partner.test-stable.shopeemobile.com/api/v2/shop/get_shop_info';

        $response11 = Http::get('https://partner.test-stable.shopeemobile.com/api/v2/shop/get_shop_info', [
            'partner_id' => $partnerId,
            'timestamp' => $timestamp,
            'access_token' => $getTokenResponse['access_token'],
            'shop_id' => $shopId,
            'sign' => $sign3
        ]);
        dd($response11->body());



        // $client = new Client();

        // $response = $client->request('GET', $host . $path, [
        //     'query' => [
        //         'partner_id' => $partnerId,
        //         'timestamp' => $timestamp,
        //         'sign' => $sign,
        //         'redirect' => $redirectUrl
        //     ]
        // ]);
        // $data = $response;
        // dd($data);

        $response = Http::withOptions([
            'allow_redirects' => [
                'max'             => 10,        // allow at most 10 redirects.
                // 'referer'         => true,      // add a Referer header
                'track_redirects' => true,
            ]
        ])->get($host . $path, [
            'partner_id' => $partnerId,
            'timestamp' => (string) $timestamp,
            'sign' => $sign,
            'redirect' => $redirectUrl
        ]);

        // dd($response);
        // dd($response->getHeaderLine('X-Guzzle-Redirect-History'));
        // dd($response->transferStats->getEffectiveUri());
        // dd($response->transferStats->getEffectiveUri()->getQuery());
        // dd(hash_hmac('sha256', $baseString, $partnerKey));

        parse_str($response->transferStats->getEffectiveUri()->getQuery(), $queryArr);

        $authUrl = 'https://open.test-stable.shopee.com/authorize';

        $response5 = Http::get($authUrl, [
            'isRedirect' => true,
            'auth_shop' => true,
            'id' => $queryArr['id'],
            'random' => $queryArr['random']
        ]);
        dd($response5->body());

        // $newUrl = (string) $response->transferStats->getEffectiveUri();

        // $response2 = Http::get($newUrl);
        // // dd($response->body());
        // dd((string) $response2->transferStats->getEffectiveUri() . ' ' . (string) $response->transferStats->getEffectiveUri());
   
        // return view('test.index');
    }
}
