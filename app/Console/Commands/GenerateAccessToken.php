<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GenerateAccessToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:init-access';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Access Token';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $shopeeAccess = new ShopeeService('/auth/token/get', 'public');

        $accessResponse = Http::post($shopeeAccess->getFullPath() . $shopeeAccess->getAccessTokenQueryString(), [
            'code' => $shopeeAccess->getCode(),
            'partner_id' => $shopeeAccess->getPartnerId(),
            'shop_id' => $shopeeAccess->getShopId()
        ]);

        $accessResponseArr = json_decode($accessResponse->body(), true);
        $refreshToken = $accessResponseArr['refresh_token'];

        $shopeeRefreshToken = new ShopeeService('/auth/access_token/get', 'public');

        $refreshTokenResponse = Http::post($shopeeRefreshToken->getFullPath() . $shopeeRefreshToken->getAccessTokenQueryString(), [
            'refresh_token' => $refreshToken,
            'partner_id' => $shopeeRefreshToken->getPartnerId(),
            'shop_id' => $shopeeRefreshToken->getShopId()
        ]);

        $refreshTokenResponseArr = json_decode($refreshTokenResponse->body(), true);

        $updatedToken = AccessToken::where('platform', 'shopee')
            ->update([
                'refresh_token' => $refreshTokenResponseArr['refresh_token'],
                'access_token' => $refreshTokenResponseArr['access_token']
            ]);

        if ($updatedToken) {
            dd('Successfully generate initial token');
        } else {
            dd('Failed to generate initial token');
        }
    }
}
