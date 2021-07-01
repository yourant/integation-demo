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
    
    public function getProductItem($sku){
        $request = new LazopRequest('/product/item/get','GET');
        $request->addApiParam('seller_sku',$sku);

        return json_decode($this->client->execute($request, $this->accessToken),true);

    }

    public function updatePriceQuantity($payload){
        $request = new LazopRequest('/product/price_quantity/update');
        $request->addApiParam('payload',$payload);

        return json_decode($this->client->execute($request, $this->accessToken),true);
    }


}