<?php

namespace App\Http\Controllers;

use LazopClient;
use LazopRequest;
use Illuminate\Http\Request;

class LazadaController extends Controller
{
    public function getProducts(){

        $accessToken = '50000801e09r7YdbrCntx1d62a424lIlSgzGtwCgyGsvayPaxsN0ZeTiPo0XOdJs';
        $c = new LazopClient('https://api.lazada.sg/rest','121343','hx4ZUElCIpS7DMOVXaqrXUvFRKfzqCCp');
        $request = new LazopRequest('/products/get','GET');
        $request->addApiParam('filter','live');
        $request->addApiParam('create_after','2021-01-01T00:00:00+0800');
        $request->addApiParam('limit','10');

        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request, $accessToken)),JSON_PRETTY_PRINT);

    }

    public function getOrders(){

        $accessToken = '50000801e09r7YdbrCntx1d62a424lIlSgzGtwCgyGsvayPaxsN0ZeTiPo0XOdJs';
        $c = new LazopClient('https://api.lazada.sg/rest','121343','hx4ZUElCIpS7DMOVXaqrXUvFRKfzqCCp');
        $request = new LazopRequest('/orders/get','GET');
        $request->addApiParam('sort_direction','DESC');
        $request->addApiParam('offset','0');
        $request->addApiParam('limit','10');
        $request->addApiParam('created_after','2021-02-10T09:00:00+08:00');
        $request->addApiParam('status','shipped');

        header('Content-Type: application/json');
        echo json_encode(json_decode($c->execute($request, $accessToken)),JSON_PRETTY_PRINT);

    }
}