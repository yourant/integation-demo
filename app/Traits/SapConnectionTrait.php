<?php

namespace App\Traits;

use GuzzleHttp\Cookie\CookieJar;
use SaintSystems\OData\ODataClient;
use GuzzleHttp\Exception\ClientException;

trait SapConnectionTrait {

    public function attemptConnection($credentials)
    {
        $isSuccess = true;
        $cookies = new CookieJar();

        $odataClient = new ODataClient(config('app.sap_path'));

        $httpProvider = $odataClient->getHttpProvider();
        $httpProvider->setExtraOptions([
            'verify' => false,
            'cookies' => $cookies
        ]);

        try {
            $odataClient->post('Login', [
                'CompanyDB' => $credentials['db'],
                'UserName' => $credentials['user_code'],
                'Password' => $credentials['pword']
            ]);
        } catch (ClientException $exception) {
            $isSuccess = $this->getResponseType($exception) == 'success';
        }

        $this->setSession($cookies);

        return $isSuccess;
    }

    public function getConnection()
    {
        $odataClient = new ODataClient(config('app.sap_path'), function($request) {
            $request->headers['Cookie'] = session('B1SESSION') . '; ' . session('ROUTEID');
        });

        $httpProvider = $odataClient->getHttpProvider();
        $httpProvider->setExtraOptions([
            'verify' => false
        ]);

        return $odataClient;
    }

    private function setSession(CookieJar $cookies)
    {
        session([
            'B1SESSION' => $cookies->getCookieByName('B1SESSION'), 
            'ROUTEID' => $cookies->getCookieByName('ROUTEID')
        ]);
    }

    private function getResponseType(ClientException $exception)
    {
        $code = $exception->getResponse()->getStatusCode();

        return $code == 200 ? 'success' : 'error';
    }
}