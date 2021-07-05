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
        $lazRequest->addApiParam('refresh_token','50001801123yHrbZ0owelyCzxcaEdt6ot1g141d0e72mdyHalxTEGWnvxoeSbCAo');
        
        print_r(json_decode($lazClient->execute($lazRequest)));

    }
}
