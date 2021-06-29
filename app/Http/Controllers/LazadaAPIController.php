<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LazadaAPIController extends Controller
{
    
    public function refreshToken(){
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/auth/token/refresh','GET');
        $request->addApiParam('refresh_token','50001800334bQUpacyNfuFKHwvqScc1jvklwZWPHeiBuhK101a2c66W0ltyxMI2s');
        
        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request)),JSON_PRETTY_PRINT);
    }
    
    public function getProductItem($sku){

        $accessToken = env('LAZADA_ACCESS_TOKEN');
        $c = new LazopClient(env('LAZADA_APP_URL'),env('LAZADA_APP_KEY'),env('LAZADA_APP_SECRET'));
        $request = new LazopRequest('/product/item/get','GET');
        $request->addApiParam('seller_sku',$sku);

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