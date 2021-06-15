<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LazadaController extends Controller
{
    
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

    public function getProductItem($sku){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/product/item/get','GET');
        $request->addApiParam('seller_sku',$sku);

        return json_decode($c->execute($request, $accessToken),true);

    }

    public function getOrder($orderId){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/order/get','GET');
        $request->addApiParam('order_id',$orderId);

        return json_decode($c->execute($request, $accessToken),true);

    }

    public function getOrders(){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','DESC');
        $request->addApiParam('offset','0');
        $request->addApiParam('limit','1');
        $request->addApiParam('created_after','2021-06-10T09:00:00+08:00');
        $request->addApiParam('status','pending');
        
        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request, $accessToken)),JSON_PRETTY_PRINT);

    }

    public function getOrderItem($orderId){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/order/items/get','GET');
        $request->addApiParam('order_id',$orderId);

        return json_decode($c->execute($request, $accessToken),true);
        
    }


}