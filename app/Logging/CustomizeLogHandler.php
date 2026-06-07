<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

class CustomizeLogHandler
{
    /**
     * Customize the given logger instance.
     *
     * @param  Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            // Only RotatingFileHandler supports setting the filename format
            if ($handler instanceof RotatingFileHandler) {
                $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');
            }

            if ($handler instanceof RotatingFileHandler || $handler instanceof StreamHandler) {
                $handler->setFormatter(new LineFormatter(
                    null,
                    null,
                    true,
                    true
                ));
            }
        }
    }
}
