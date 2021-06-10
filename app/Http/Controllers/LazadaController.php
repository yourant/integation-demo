<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Illuminate\Http\Request;

class LazadaController extends Controller
{
    public function getCategoryTree(){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/category/tree/get','GET');

        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request)),JSON_PRETTY_PRINT);
    
    }

    public function getProducts(){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/products/get','GET');
        $request->addApiParam('filter','live');
        $request->addApiParam('create_after','2021-01-01T00:00:00+0800');
        $request->addApiParam('limit','10');

        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request, $accessToken)),JSON_PRETTY_PRINT);

    }

    public function getOrders(){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','DESC');
        $request->addApiParam('offset','0');
        $request->addApiParam('limit','10');
        $request->addApiParam('created_after','2021-02-10T09:00:00+08:00');
        $request->addApiParam('status','shipped');

        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request, $accessToken)),JSON_PRETTY_PRINT);

    }

    public function getSeller(){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/seller/get','GET');

        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request, $accessToken)),JSON_PRETTY_PRINT);
    
    }
}