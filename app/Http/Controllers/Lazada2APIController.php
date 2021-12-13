<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Illuminate\Http\Request;
use App\Services\Lazada2Service;

class Lazada2APIController extends Controller
{
    protected $accessToken;
    protected $client;
    protected $dateStart;

    public function __construct(){
        $lazada2Service = new Lazada2Service();
        $this->client = new LazopClient($lazada2Service->getAppUrl(),$lazada2Service->getAppKey(),$lazada2Service->getAppSecret());
        $this->accessToken = $lazada2Service->getAccessToken();
        $this->dateStart = date('Y-m-d', strtotime('-16 days')).'T23:59:59+08:00'; // Output: Date start will be yesterday until today.
    }

    public function getProducts($skus){
        $request = new LazopRequest('/products/get','GET');
        $request->addApiParam('filter','all');
        $request->addApiParam('sku_seller_list',$skus);

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
        $request->addApiParam('status','pending');
        $request->addApiParam('created_after',$this->dateStart);
        
        return json_decode($this->client->execute($request, $this->accessToken),true);

    }

    public function getReadyToShipOrders($offset){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','ASC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset',$offset);
        $request->addApiParam('limit',50);
        $request->addApiParam('status','ready_to_ship');
        $request->addApiParam('update_after',$this->dateStart);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function getReturnedOrders($offset){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','ASC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset',$offset);
        $request->addApiParam('limit',50);
        $request->addApiParam('status','returned');
        $request->addApiParam('update_after',$this->dateStart);
        
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


}
