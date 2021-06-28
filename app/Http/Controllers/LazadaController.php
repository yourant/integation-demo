<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LazadaController extends Controller
{
    
    public function refreshToken(){
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/auth/token/refresh','GET');
        $request->addApiParam('refresh_token','50001800334bQUpacyNfuFKHwvqScc1jvklwZWPHeiBuhK101a2c66W0ltyxMI2s');
        
        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request)),JSON_PRETTY_PRINT);
    }
    public function getProducts($skus){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/products/get','GET');
        $request->addApiParam('filter','live');
        $request->addApiParam('sku_seller_list',$skus);

        return json_decode($c->execute($request, $accessToken),true);

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
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('limit','2');
        $request->addApiParam('created_before','2021-06-01T16:00:00+08:00');
        $request->addApiParam('created_after','2017-05-31T09:00:00+08:00');
        
        return json_decode($c->execute($request, $accessToken),true);

    }

    public function getOrderItem($orderId){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/order/items/get','GET');
        $request->addApiParam('order_id',$orderId);

        return json_decode($c->execute($request, $accessToken),true);
        
    }

    public function getMultipleOrderItems($orderIds){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/orders/items/get','GET');
        $request->addApiParam('order_ids',$orderIds);

        return json_decode($c->execute($request, $accessToken),true);

    }

    public function updatePriceQuantity($payload){
        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/product/price_quantity/update');
        $request->addApiParam('payload',$payload);

        return json_decode($c->execute($request, $accessToken),true);
    }



}