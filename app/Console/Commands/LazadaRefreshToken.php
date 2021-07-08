<?php

namespace App\Console\Commands;

use LazopClient;
use LazopRequest;
use App\Services\LazadaService;
use Illuminate\Console\Command;

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
        //Lazada Service
        $lazService = new LazadaService();
        //Lazada SDK
        $lazClient = new LazopClient($lazService->getAppUrl(),$lazService->getAppKey(),$lazService->getAppSecret());
        $lazRequest = new LazopRequest('/auth/token/refresh','GET');
        $lazRequest->addApiParam('refresh_token',config('app.lazada_refresh_token'));
        $response = json_decode($lazClient->execute($lazRequest));
        $path = base_path('.env');
        
        if(file_exists($path)){
            file_put_contents($path, str_replace(
                'LAZADA_REFRESH_TOKEN='.config('app.lazada_refresh_token'), 'LAZADA_REFRESH_TOKEN='.$response->refresh_token, file_get_contents($path)
            ));
            file_put_contents($path, str_replace(
                'LAZADA_ACCESS_TOKEN='.config('app.lazada_access_token'), 'LAZADA_ACCESS_TOKEN='.$response->access_token, file_get_contents($path)
            ));
        }else{
            echo 'File does not exist';
        }
        
        echo json_encode($response,JSON_PRETTY_PRINT);


    }
}
