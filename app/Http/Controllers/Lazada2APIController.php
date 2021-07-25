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
        $request->addApiParam('update_after',$this->dateStart);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }

    public function getReturnedOrders(){
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','DESC');
        $request->addApiParam('sort_by','created_at');
        $request->addApiParam('offset','0');
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