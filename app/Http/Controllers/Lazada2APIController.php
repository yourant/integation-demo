<?php

namespace App\Http\Controllers;

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
}
