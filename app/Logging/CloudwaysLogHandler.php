<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

class CloudwaysLogHandler
{
    /**
     * Customize the given logger instance for Cloudways hosting.
     *
     * @param  Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof RotatingFileHandler) {
                // Set file permissions when rotating files
                $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');

                // Override the write method to handle permissions
                $reflection = new \ReflectionClass($handler);
                $urlProperty = $reflection->getProperty('url');
                $urlProperty->setAccessible(true);

                $originalUrl = $urlProperty->getValue($handler);
                if ($originalUrl && file_exists($originalUrl)) {
                    @chmod($originalUrl, 0666);
                }
            } elseif ($handler instanceof StreamHandler) {
                $reflection = new \ReflectionClass($handler);
                $urlProperty = $reflection->getProperty('url');
                $urlProperty->setAccessible(true);

                $originalUrl = $urlProperty->getValue($handler);
                if ($originalUrl && file_exists($originalUrl)) {
                    @chmod($originalUrl, 0666);
                }
            }
        }
    }
}
