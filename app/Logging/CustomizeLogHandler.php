<?php

namespace App\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

class CustomizeLogHandler
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof RotatingFileHandler || $handler instanceof StreamHandler) {
                $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');
                $handler->setFormatter(new \Monolog\Formatter\LineFormatter(
                    null,
                    null,
                    true,
                    true
                ));
            }
        }
    }
} 