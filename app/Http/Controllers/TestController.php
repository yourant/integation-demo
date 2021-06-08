<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
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
        // dd(time());
        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $partnerId = '1000909';
        $partnerKey = 'e1b4853065602808a3647497ddde7568daa575c459de48a99b074d97bc9244d0';

        $baseString = $partnerId . $path . $timestamp;

        $sign = hash_hmac('sha256', $baseString, $partnerKey);
        
        dd(hash_hmac('sha256', $baseString, $partnerKey));

        return view('test.index');
    }
}
