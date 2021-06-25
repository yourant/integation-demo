<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LazadaLoginController;

class LazadaPriceList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazada:price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        //1844845298 - Use this Lazada product id for testing purposes. Look this on inactive products tab in Lazada
        $odataClient = (new LazadaLoginController)->login();

        $items = $odataClient->from('Items')->find('TK0001'); // ItemPrices[8]['Price']

        print_r($items);
    }
}
