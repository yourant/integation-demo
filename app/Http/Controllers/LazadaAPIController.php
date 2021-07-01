<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Illuminate\Http\Request;
use App\Services\LazadaService;
use Illuminate\Support\Facades\Http;

class LazadaAPIController extends Controller
{
    protected $accessToken;
    protected $client;

    public function __construct(){
        $lazadaService = new LazadaService();
        $this->client = new LazopClient($lazadaService->getAppUrl(),$lazadaService->getAppKey(),$lazadaService->getAppSecret());
        $this->accessToken = $lazadaService->getAccessToken();
    
    }

    public function getProducts($skus){
        $request = new LazopRequest('/products/get','GET');
        $request->addApiParam('filter','live');
        $request->addApiParam('sku_seller_list',$skus);

        return json_decode($this->client->execute($request, $this->accessToken),true);
        
    }

    public function getProductItem($sku){
        $request = new LazopRequest('/product/item/get','GET');
        $request->addApiParam('seller_sku',$sku);

        return json_decode($this->client->execute($request, $this->accessToken),true);

    }

    public function getOrder($orderId){
        $request = new LazopRequest('/order/get','GET');
        $request->addApiParam('order_id',$orderId);

        return json_decode($this->client->execute($request, $this->accessToken),true);

    }

    public function getOrders(){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','DESC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('limit','2');
        $request->addApiParam('created_before','2021-06-01T16:00:00+08:00');
        $request->addApiParam('created_after','2017-05-31T09:00:00+08:00');
        
        return json_decode($this->client->execute($request, $this->accessToken),true);

    }

    public function getOrderItem($orderId){
        $request = new LazopRequest('/order/items/get','GET');
        $request->addApiParam('order_id',$orderId);

        return json_decode($this->client->execute($request, $this->accessToken),true);
        
    }

    public function getMultipleOrderItems($orderIds){
        $request = new LazopRequest('/orders/items/get','GET');
        $request->addApiParam('order_ids',$orderIds);

        return json_decode($this->client->execute($request, $this->accessToken),true);

    }
}