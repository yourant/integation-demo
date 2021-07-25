<?php
namespace App\Services;

use App\Models\AccessToken;

class Lazada2Service{

    protected $appUrl;
    protected $appKey;
    protected $appSecret;
    protected $path;
    protected $code;
    protected $accessToken;

    public function __construct()
    {
        $lazadaToken = AccessToken::where('platform','lazada2')->first();

        $this->appUrl = config('app.lazada2_app_url');
        $this->appKey = config('app.lazada2_app_key');
        $this->appSecret = config('app.lazada2_app_secret');
        $this->accessToken = $lazadaToken->access_token;

    }

    public function getAppUrl()
    {
        return $this->appUrl;
    }

    public function getAppKey()
    {
        return $this->appKey;
    }

    public function getAppSecret()
    {
        return $this->appSecret;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function setAccessToken($token)
    {
        $this->accessToken = $token;
    }
}