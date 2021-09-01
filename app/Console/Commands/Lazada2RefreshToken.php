<?php

namespace App\Console\Commands;

use LazopClient;
use LazopRequest;
use Carbon\Carbon;
use App\Models\AccessToken;
use Illuminate\Console\Command;
use App\Services\Lazada2Service;
use Illuminate\Support\Facades\Log;

class Lazada2RefreshToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada2:refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Access Token for Lazada 2nd Account Access';

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
            $lazadaToken = AccessToken::where('platform','lazada2')->first();
            $updatedAt = $lazadaToken->updated_at->format('Y-m-d');
            $checkDate = date('Y-m-d',strtotime($updatedAt. ' + 28 days'));
            $now = Carbon::now()->format('Y-m-d');
            if($updatedAt != $now && $checkDate == $now){
                //Lazada Service
                $lazService = new Lazada2Service();
                //Lazada SDK
                $lazClient = new LazopClient($lazService->getAppUrl(),$lazService->getAppKey(),$lazService->getAppSecret());
                $lazRequest = new LazopRequest('/auth/token/refresh');
                $lazRequest->addApiParam('refresh_token',$lazadaToken->refresh_token);
                $response = json_decode($lazClient->execute($lazRequest));
                //Update Token
                $updatedToken = AccessToken::where('platform', 'lazada2')
                        ->update([
                            'refresh_token' => $response->refresh_token,
                            'access_token' => $response->access_token
                        ]);

                if($updatedToken) {
                    Log::channel('lazada2.refresh_token')->info('New tokens generated.');
                } else {
                    Log::channel('lazada2.refresh_token')->info('Problem while generating tokens.');
                }
                
            }
            
        }

        catch(\Exception $e)
        {
            Log::channel('lazada2.refresh_token')->emergency($e->getMessage());
        }
        
    }
}
