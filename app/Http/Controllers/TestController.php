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
        $salesOrderSapService = new SapService();

        $shopeeAccess = new ShopeeService('/auth/token/get', 'public');

        $accessResponse = Http::post($shopeeAccess->getFullPath() . $shopeeAccess->getAccessTokenQueryString(), [
            'code' => $shopeeAccess->getCode(),
            'partner_id' => $shopeeAccess->getPartnerId(),
            'shop_id' => $shopeeAccess->getShopId()
        ]);
        
        $accessResponseArr = json_decode($accessResponse->body(), true);
        
        $orderList = [];
        $moreReadyOrders = true;
        $offset = 0;
        $pageSize = 2;
        
        while ($moreReadyOrders) {
            $shopeeReadyOrders = new ShopeeService('/order/get_order_list', 'shop', $accessResponseArr['access_token']);
            $shopeeReadyOrdersResponse = Http::get($shopeeReadyOrders->getFullPath(), array_merge([
                'time_range_field' => 'create_time',
                'time_from' => 1623970808,
                'time_to' => 1624575608,
                'page_size' => $pageSize,
                'cursor' => $offset,
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
