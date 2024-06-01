<?php
namespace App\Utils;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class LogManager
{
    private $logger;

    public function __construct($name = 'app')
    {
        $this->logger = new Logger($name);
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../logs/app.log', Logger::DEBUG));
    }

    public function getLogger()
    {
        return $this->logger;
    }
}

