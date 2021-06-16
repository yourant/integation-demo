<?php

namespace App\Services;

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
        $loginResponse = Http::withOptions([
            'verify' => false,
        ])->post($this->host . '/Login', [
            'CompanyDB' => $this->db,
            'Password' => $this->password,
            'UserName' => $this->username
        ]);

        $this->cookieStr = $loginResponse->header('Set-Cookie');
    }
}