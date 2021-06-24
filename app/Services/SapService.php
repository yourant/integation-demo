<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use SaintSystems\OData\ODataClient;
use Illuminate\Support\Facades\Http;

class SapService
{
    protected $host;
    protected $db;
    protected $password;
    protected $username;
    protected $cookieStr;
    protected $odataClient;

    public function __construct()
    {
        $this->host = config('app.sap_path');
        $this->db = config('app.sap_db');
        $this->password = config('app.sap_pword');
        $this->username = config('app.sap_user');

        $this->authenticate();
        $this->setOdataClient($this->host, $this->cookieStr);
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getCookieStr()
    {
        return $this->cookieStr;
    }

    public function getOdataClient()
    {
        return $this->odataClient;
    }

    public function setOdataClient($host, $cookieStr)
    {
        $this->odataClient = new ODataClient($host, function($request) use($cookieStr) {
            //set the header Set-cookie (from the authentication route) as cookie in the next request
            $request->headers['Cookie'] = $cookieStr;
        });

        $httpProvider = $this->odataClient->getHttpProvider();
        $httpProvider->setExtraOptions([
            'verify' => false
        ]);
    }

    private function authenticate()
    {
        $cookies = new CookieJar();

        $loginResponse = Http::withOptions([
            'verify' => false,
            'cookies' => $cookies
        ])->post($this->host . '/Login', [
            'CompanyDB' => $this->db,
            'Password' => $this->password,
            'UserName' => $this->username
        ]);

        $b1Session = $cookies->getCookieByName('B1SESSION');
        $routeId = $cookies->getCookieByName('ROUTEID');

        $this->cookieStr = 'B1SESSION=' . $b1Session->getValue() . '; ROUTEID=' . $routeId->getValue();
    }
}