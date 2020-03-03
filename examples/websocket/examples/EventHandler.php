<?php

use Bobby\Websocket\Pusher;

class EventHandler extends \Bobby\Websocket\EventHandlerContract
{
    public function onMessage($socket, $frame)
    {
        $data = json_decode($frame->payloadData);
        $data->time = date('Y-m-d H:i:s');
        $data = json_encode($data);
   
        // 只能获得当前进程所维护的客户端连接.所以这个例子中进程数只能是1
        foreach ($this->server->connections as $connection) {
            Pusher::pushString($connection, $data);
        }
    }
}