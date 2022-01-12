<?php

namespace App\Services;

class TchubService
{
    protected $baseUrl;
    protected $basePath;
    protected $storeCode;
    protected $apiVersion;
    protected $accessToken;

    public function __construct($path, $storeCode='')
    {
        $this->baseUrl = config('app.tchub_base_url');
        $this->basePath = config('app.tchub_base_path');
        $this->storeCode = $storeCode;
        $this->apiVersion = config('app.tchub_api_version');
        $this->accessToken = config('app.tchub_access_token');
        $this->path = $path;
    }

    public function constructRequest()
    {
        $request = $this->baseUrl;
        $request .= '/'.$this->basePath;

        if (!empty($this->storeCode)) {
            $request .= '/'.$this->storeCode;
        }

        $request .= '/'.$this->apiVersion;
        
        return $request;
    }

    public function getFullPath()
    {
        return $this->constructRequest() . $this->path;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }
}