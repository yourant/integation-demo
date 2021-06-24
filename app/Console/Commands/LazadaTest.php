<?php

namespace App\Console\Commands;

use LazopClient;
use LazopRequest;
use Illuminate\Console\Command;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\LazadaLoginController;

class LazadaTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is for Lazada testing endpoints.';

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
        
    }

        
}

