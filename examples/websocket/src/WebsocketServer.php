<?php
namespace Bobby\Websocket;

use Bobby\MultiProcesses\Pool;
use Bobby\MultiProcesses\Quit;
use Bobby\MultiProcesses\Worker;
use Bobby\ServerNetworkProtocol\Websocket\Parser;

class WebsocketServer
{
    protected $config;

    protected $eventHandler;

    public $readers = []; // 所有监听socket

    public $connections = []; // 所有已握手连接

    public $listen; // 服务监听的socket

    protected $parser;

    public function __construct(ServerConfig $config, EventHandlerContract $eventHandler)
    {
        if (!$config->address || !$config->port || !$config->workerNum) {
            throw new \InvalidArgumentException("instance of " . get_class($config) . " must to set address or port, worker number.");
        }

        $this->config = $config;

        $eventHandler->bindServer($this);
        $this->eventHandler = $eventHandler;
        $this->parser = new Parser();
    }

    public function run()
    {
        switch ($this->config->mode) {
            case ServerConfig::SELECT_MODE:
                $callback = [$this, 'select'];
        }

        $pool = new Pool($this->config->workerNum, new Worker(function () use ($callback) {
            call_user_func($callback);
        }));

        pcntl_signal(SIGTERM, function ($signo) use ($pool) {
            $workerNum = $pool->getWorkersNum();
            for ($i = 0; $i < $workerNum; $i++) {
                posix_kill($pool->getWorker()->getPid(), SIGKILL);
            }
            Quit::normalQuit();
        });

        pcntl_signal(SIGCHLD, function ($signo) use($pool) {
            while (($pid = pcntl_wait($status, WNOHANG)) > 0) {
                $workerNum = $pool->getWorkersNum();
                for ($i = 0; $i < $workerNum; $i++) {
                    if (($worker = $pool->getWorker())->getPid() == $pid) {
                        $worker->run();
                        break;
                    }
                }
            }
        });

        $pool->run();
        Pool::collect();
    }

    protected function listenSocket()
    {
        $contextOption['socket']['so_reuseport'] = 1;
        $context = stream_context_create($contextOption);
        if (!$this->listen = stream_socket_server("tcp://{$this->config->address}:{$this->config->port}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context)) {
            throw new \Exception($errstr, $errno);
        }
        stream_set_blocking($this->listen, false);
    }

    protected function select()
    {
        $this->listenSocket();
        $this->readers[] = $this->listen;

        $writers = $exceptions = [];
        while (true) {
            $readers = $this->readers;
            @stream_select($readers, $writers, $exceptions, null);

            foreach ($readers as $key => $reader) {
                if ($reader === $this->listen) {
                    $this->acceptConnection($reader);
                } else {
                    if (!$this->isShackedConnection($reader)) {
                        $this->shackWithConnection($reader);
                    } else {
                        if (!$buff = stream_get_contents($reader)) {
                            $this->dealLostPackage($reader);
                            continue;
                        }

                        $this->parser->input($buff);
                        if (empty($frames = $this->parser->decode())) {
                            continue;
                        }

                        foreach ($frames as $frame) {
                            switch ($frame->opcode) {
                                case OpcodeEnum::PING:
                                    $this->eventHandler->onPing($reader, $frame);
                                    break;
                                case OpcodeEnum::OUT_CONNECT:
                                    $this->eventHandler->onOutConnect($reader, $frame);
                                    break;
                                case OpcodeEnum::TEXT:
                                case OpcodeEnum::PONG:
                                    $this->eventHandler->onMessage($reader, $frame);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function dealLostPackage($socket)
    {
        if (method_exists($this->eventHandler, "onLostPackage")) {
            $this->eventHandler->onLostPackage($socket);
        }
    }

    protected function acceptConnection($socket)
    {
        $connection = stream_socket_accept($socket);
        $this->readers[(intval($socket))] = $connection;
        stream_set_blocking($connection, false);
    }

    protected function shackWithConnection($socket)
    {
        if (!$header = stream_get_contents($socket)) {
            return $this->dealLostPackage($socket);
        }

        if (method_exists($this->eventHandler, "onShack")) {
            $this->eventHandler->onShack(new ShackHttpRequest($header), $response = new RefuseShackResponse());

            if ($response->hasErrors()) {
                return $response->response($socket);
            }
        }

        $key = substr($header, strpos($header, "Sec-WebSocket-Key:") + 18);
        $key = trim(substr($key, 0, strpos($key, "\r\n")));
        $newKey = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

        $newHeader = "HTTP/1.1 101 Switching Protocols\r\n";
        $newHeader .= "Upgrade: websocket\r\n";
        $newHeader .= "Sec-WebSocket-Version: 13\r\n";
        $newHeader .= "Connection: Upgrade\r\n";
        $newHeader .= "Sec-WebSocket-Accept: $newKey\r\n\r\n";

        fwrite($socket, $newHeader);
       
        $this->connections[intval($socket)] = $socket;
        $this->readers[intval($socket)] = $socket;

        if (method_exists($this->eventHandler, 'onConnection')) {
            $this->eventHandler->onConnection($socket);
        }
    }

    protected function isShackedConnection($connection)
    {
        return in_array($connection, $this->connections);
    }
}