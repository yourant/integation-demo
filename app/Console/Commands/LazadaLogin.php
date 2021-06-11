<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LazadaLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:login';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lazada Login';

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
        $response = Http::withOptions([
            'verify' => false,
        ])->post('https://192.168.18.140:50000/b1s/v1/Login', [
            'CompanyDB' => 'TC_DEV',
            'Password' => '4021',
            'UserName' => 'kass',
        ]);

        if($response){
            echo 'Login Success';
        }else{
            echo 'Login Failed';
        }
    }
}
