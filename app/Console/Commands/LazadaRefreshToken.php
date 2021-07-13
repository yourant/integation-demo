<?php

namespace App\Console\Commands;

use DateTime;
use LazopClient;
use LazopRequest;
use App\Models\AccessToken;
use App\Services\LazadaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LazadaRefreshToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Access Token for Lazada Access';

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
        try
        {
            $lazadaToken = AccessToken::where('platform','lazada')->first();
            //Lazada Service
            $lazService = new LazadaService();
            //Lazada SDK
            $lazClient = new LazopClient($lazService->getAppUrl(),$lazService->getAppKey(),$lazService->getAppSecret());
            $lazRequest = new LazopRequest('/auth/token/refresh');
            $lazRequest->addApiParam('refresh_token',$lazadaToken->refresh_token);
            $response = json_decode($lazClient->execute($lazRequest));
             //Update Token
            $updatedToken = AccessToken::where('platform', 'lazada')
                    ->update([
                        'refresh_token' => $response->refresh_token,
                        'access_token' => $response->access_token
                    ]);

            if($updatedToken) {
                Log::channel('lazada.refresh_token')->info('New tokens generated.');
            } else {
                Log::channel('lazada.refresh_token')->info('Problem while generating tokens.');
            }
        }

        catch(\Exception $e)
        {
            Log::channel('lazada.refresh_token')->emergency($e->getMessage());
        }


    }
}
