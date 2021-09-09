<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use App\Logging\CustomizeFormatter;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'lazada' => [
            'refresh_token' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada/refresh_token.log'),
                'level' => 'debug'
            ],
            'item_master' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada/item_master.log'),
                'level' => 'debug'
            ],
            'sales_order' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada/sales_order.log'),
                'level' => 'debug'
            ],
            'ar_invoice' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada/ar_invoice.log'),
                'level' => 'debug'
            ],
            'credit_memo' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada/credit_memo.log'),
                'level' => 'debug'
            ]
        ],
        
        'lazada2' => [
            'refresh_token' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada2/refresh_token.log'),
                'level' => 'debug'
            ],
            'item_master' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada2/item_master.log'),
                'level' => 'debug'
            ],
            'sales_order' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada2/sales_order.log'),
                'level' => 'debug'
            ],
            'ar_invoice' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada2/ar_invoice.log'),
                'level' => 'debug'
            ],
            'credit_memo' => [
                'driver' => 'single',
                'path' => storage_path('logs/lazada2/credit_memo.log'),
                'level' => 'debug'
            ]
        ]
    ],

];
