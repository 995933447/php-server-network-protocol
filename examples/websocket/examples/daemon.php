<?php
require __DIR__ . '/../vendor/autoload.php';
require 'EventHandler.php';

use Bobby\MultiProcesses\Process;
use Bobby\Websocket\ServerConfig;
use Bobby\Websocket\WebsocketServer;

$process = new Process(function () {
    $config = new ServerConfig();
    $config->setAddress("0.0.0.0");
    $config->setPort(8901);
    $config->setWorkerNum(1);
    (new WebsocketServer($config, new EventHandler()))->run();
}, true);
$process->setName('php websocket server');
$process->run();
