<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SaintSystems\OData\ODataClient;
use Illuminate\Support\Facades\Http;

class SAPLoginController extends Controller
{
    public function login(){

        $response = Http::withOptions([
            'verify' => false,
        ])->post('https://192.168.18.140:50000/b1s/v1/Login', [
            'CompanyDB' => 'TC_DEV',
            'Password' => '4021',
            'UserName' => 'kass'
        ]);

        $odataServiceUrl = 'https://192.168.18.140:50000/b1s/v1';

        $odataClient = new ODataClient($odataServiceUrl, function($request) use($response) {
            $request->headers['Cookie'] = $response->header('Set-Cookie');
        });

        $httpProvider = $odataClient->getHttpProvider();
        $httpProvider->setExtraOptions([
            'verify' => false
        ]);

        return $odataClient;

    }
}
