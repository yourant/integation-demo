<?php

namespace App\Services;

use App\Models\AccessToken;

class ShopeeService
{
    protected $timestamp;
    protected $partnerId;
    protected $partnerKey;
    protected $redirectUrl;
    protected $apiVersUrl;
    protected $host;
    protected $path;
    protected $shopId;
    protected $code;
    protected $accessToken;
    protected $baseString;
    protected $sign;

    public function __construct($path, $accessLevel, $accessToken = null)
    {
        $shopeeConfig = AccessToken::where('platform', 'shopee')->first();

        $this->timestamp = time();
        $this->partnerId = (int) config('app.shopee_partner_id');
        $this->partnerKey = config('app.shopee_partner_key');
        $this->redirectUrl = config('app.url') . 'test2';
        $this->apiVersUrl = config('app.shopee_api_vers_url');
        $this->host = config('app.shopee_host');
        $this->path = $path;
        $this->accessToken = $accessToken;
        $this->shopId = $shopeeConfig->shop_id;
        $this->code = $shopeeConfig->code;

        $this->setBaseString($accessLevel);
        $this->setSign($this->baseString);
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function getPartnerId()
    {
        return $this->partnerId;
    }

    public function getPartnerKey()
    {
        return $this->partnerKey;
    }

    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    public function getApiVersUrl()
    {
        return $this->apiVersUrl;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getShopId()
    {
        return $this->shopId;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function getBaseString()
    {
        return $this->baseString;
    }

    public function getSign()
    {
        return $this->sign;
    }

    public function getPath()
    {
        return $this->apiVersUrl . $this->path;
    }

    public function getFullPath()
    {
        return $this->getHost() . $this->getPath();
    }

    public function getAccessTokenQueryString()
    {
        $data = array(
            'sign' => $this->sign,
            'partner_id' => $this->partnerId,
            'timestamp' => $this->timestamp
        );

        return '?' . http_build_query($data);
    }

    public function getShopQueryString()
    {
        $data = array(
            'sign' => $this->sign,
            'partner_id' => $this->partnerId,
            'timestamp' => $this->timestamp,
            'shop_id' => $this->shopId,
            'access_token' => $this->accessToken
        );

        return '?' . http_build_query($data);
    }

    public function getShopCommonParameter()
    {
        return array(
            'sign' => $this->sign,
            'partner_id' => $this->partnerId,
            'timestamp' => $this->timestamp,
            'shop_id' => $this->shopId,
            'access_token' => $this->accessToken
        );
    }

    public function setAccessToken($token)
    {
        $this->accessToken = $token;
    }

    public function setBaseString($accessLevel)
    {
        $this->baseString = $this->partnerId . $this->getPath() . $this->timestamp;

        if ($accessLevel == 'shop') {
            
            $this->baseString = $this->baseString . $this->accessToken . $this->shopId;
        }
    }

    public function setSign($baseString)
    {
        $this->sign = hash_hmac("sha256", $baseString, $this->partnerKey);
    }
}