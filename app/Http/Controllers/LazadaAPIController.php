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
        $this->dateStart = date('Y-m-d', strtotime('-16 days')).'T23:59:59+08:00'; // Output: Date start will be 15 days until today.
    }

    public function getProducts($skus){
        $request = new LazopRequest('/products/get','GET');
        $request->addApiParam('filter','all');
        $request->addApiParam('sku_seller_list',$skus);

        return json_decode($this->client->execute($request, $this->accessToken),true);   
    }

    public function getProductItem($sku){
        $request = new LazopRequest('/product/item/get','GET');
        $request->addApiParam('seller_sku',$sku);

        return json_decode($this->client->execute($request, $this->accessToken),true);   
    }

    public function createProduct($payload){
        $request = new LazopRequest('/product/create');
        $request->addApiParam('payload',$payload);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function getPendingOrders($offset){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','ASC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset',$offset);
        $request->addApiParam('limit',50);
        $request->addApiParam('status','delivered');
        $request->addApiParam('created_after','2021-11-02T14:00:00+08:00');
        $request->addApiParam('created_before','2021-11-02T14:02:00+08:00');
        
        return json_decode($this->client->execute($request, $this->accessToken),true);

    }

    public function getReadyToShipOrders($offset){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','ASC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset',$offset);
        $request->addApiParam('limit',50);
        $request->addApiParam('status','delivered');
        $request->addApiParam('created_after','2021-11-02T14:00:00+08:00');
        $request->addApiParam('created_before','2021-11-02T14:02:00+08:00');

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function getReturnedOrders($offset){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','ASC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset',$offset);
        $request->addApiParam('limit',50);
        $request->addApiParam('status','delivered');
        $request->addApiParam('created_after','2021-11-02T14:00:00+08:00');
        $request->addApiParam('created_before','2021-11-02T14:02:00+08:00');
        
        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function getMultipleOrderItems($orderIds){
        $request = new LazopRequest('/orders/items/get','GET');
        $request->addApiParam('order_ids',$orderIds);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function updatePriceQuantity($payload){
        $request = new LazopRequest('/product/price_quantity/update');
        $request->addApiParam('payload',$payload);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function activateProduct($payload){
        $request = new LazopRequest('/product/update');
        $request->addApiParam('payload',$payload);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function deactivateProduct($payload){
        $request = new LazopRequest('/product/deactivate');
        $request->addApiParam('apiRequestBody',$payload);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }


}