<?php
namespace App\Utils;

use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;

class LogManager
{
    private $logger;

    public function __construct($name = 'app')
    {
        $this->logger = new Logger($name);
        $this->logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::DEBUG));
    }

    public function getLogger()
    {
        return $this->logger;
    }
}

