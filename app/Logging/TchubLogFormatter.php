<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class TchubLogFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] : %message% %context%\r\n"
            ));
        }
    }
}