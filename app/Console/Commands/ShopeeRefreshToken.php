<?php

namespace App\Console\Commands;

use App\Models\AccessToken;
use App\Services\LogService;
use App\Services\ShopeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ShopeeRefreshToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate Refresh Token';

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
        $shopeeToken = AccessToken::where('platform', 'shopee')->first();
        $logger = new LogService('general'); 

        $successMsg = 'Successfully refreshed token';
        $failMsg = 'Failed to refresh token';
        $shopeeErrorMsg = 'Failed to retrieve data from the Shopee API';

        $logger->writeLog('EXECUTING REFRESH TOKEN SCRIPT . . .');

        $shopeeRefreshToken = new ShopeeService('/auth/access_token/get', 'public');

        $refreshTokenResponse = Http::post($shopeeRefreshToken->getFullPath() . $shopeeRefreshToken->getAccessTokenQueryString(), [
            'refresh_token' => $shopeeToken->refresh_token,
            'partner_id' => $shopeeRefreshToken->getPartnerId(),
            'shop_id' => (int) $shopeeRefreshToken->getShopId()
        ]);

        $refreshTokenResponseArr = $logger->validateResponse(json_decode($refreshTokenResponse->body(), true));
        
        if ($refreshTokenResponseArr) {
            $updatedToken = $shopeeToken->update([
                'refresh_token' => $refreshTokenResponseArr['refresh_token'],
                'access_token' => $refreshTokenResponseArr['access_token']
            ]);

            if ($updatedToken) {
                $logger->writeLog($successMsg);
                $this->info($successMsg);
            } else {
                $logger->writeLog($failMsg, true);
                $this->error($failMsg);
            }
        } else {
            $logger->writeLog($shopeeErrorMsg, true);
            $this->error($shopeeErrorMsg);
        }
    }
}
