<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\LazadaRefreshToken::class,
        Commands\LazadaItemMaster::class,
        Commands\LazadaOrder::class,
        Commands\LazadaInvoice::class,
        Commands\LazadaCreditMemo::class,
        Commands\PriceAndStockShopeeUpdate::class,
        Commands\ShopeeFirstScheduler::class,
        Commands\SalesOrderShopeeCreate::class,
        Commands\InvoiceShopeeCreate::class,
        Commands\ShopeeItemCreate::class,
        Commands\ShopeeDisableIntegration::class,
        Commands\ShopeeEnableIntegration::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //Lazada Commands
        $schedule->command('lazada:refresh-token');
        $schedule->command('lazada:item-master');
        $schedule->command('lazada:sales-order');
        $schedule->command('lazada:ar-invoice');
        $schedule->command('lazada:credit-memo');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
