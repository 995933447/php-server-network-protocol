<?php
namespace Bobby\Websocket;

abstract class EventHandlerContract
{
    protected $server;

    public function bindServer(WebsocketServer $server)
    {
        $this->server = $server;
    }

    abstract public function onMessage($connection, $frame);

    public function onPing($connection, $frame)
    {
        Pusher::pong($connection);
    }

    public function onOutConnect($connection, $frame)
    {
        $this->outConnect($connection);
    }

    public function outConnect($connection)
    {
        Pusher::notifyClose($connection);
        fclose($connection);
        unset($this->server->connections[intval($connection)]);
        unset($this->server->readers[intval($connection)]);
    }
}