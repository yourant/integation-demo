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
    protected $dateStart;

    public function __construct(){
        $lazadaService = new LazadaService();
        $this->client = new LazopClient($lazadaService->getAppUrl(),$lazadaService->getAppKey(),$lazadaService->getAppSecret());
        $this->accessToken = $lazadaService->getAccessToken();
        $this->dateStart = date('Y-m-d', strtotime('-2 days')).'T23:59:59+08:00'; // Output: Date start will be yesterday until today.
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

    public function getPendingOrders(){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','ASC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset','0');
        $request->addApiParam('status','pending');
        $request->addApiParam('created_after',$this->dateStart);
        
        return json_decode($this->client->execute($request, $this->accessToken),true);

    }

    public function getReadyToShipOrders(){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','ASC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset','0');
        $request->addApiParam('status','ready_to_ship');
        $request->addApiParam('created_after', '2021-07-04T12:00:00+08:00');
        $request->addApiParam('created_before', '2021-07-04T15:00:00+08:00');

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function getReturnedOrders(){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','DESC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset','0');
        $request->addApiParam('status','returned');
        $request->addApiParam('created_after',$this->dateStart);
        
        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function getMultipleOrderItems($orderIds){
        $request = new LazopRequest('/orders/items/get','GET');
        $request->addApiParam('order_ids',$orderIds);

        return json_decode($this->client->execute($request, $this->accessToken),true);

    }
}