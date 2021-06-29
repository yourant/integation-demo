<?php
namespace App\Services;

class LazadaService{

    protected $appUrl;
    protected $appKey;
    protected $appSecret;
    protected $path;
    protected $code;
    protected $accessToken;

    public function __construct($path, $accessToken = null)
    {
        $this->appUrl = config('app.lazada_app_url');
        $this->appKey = config('app.lazada_app_key');
        $this->appSecret = config('app.lazada_app_secret');
        $this->accessToken = $accessToken;
        
        $this->code = '0_121343_vymRW4nShmAnrG13TfDcOOEQ28591';

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

    public function getCode()
    {
        return $this->code;
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