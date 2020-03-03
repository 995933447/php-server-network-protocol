<?php
namespace Bobby\Websocket;

class Pusher
{
    public static function ping($socket)
    {
        return fwrite($socket, (new Frame())->encodeServerMessage(OpcodeEnum::PING, '')->buff);
    }

    public static function pong($socket)
    {
        return fwrite($socket, (new Frame())->encodeServerMessage(OpcodeEnum::PONG, '')->buff);
    }

    public static function pushString($socket, $message)
    {
        return fwrite($socket, (new Frame())->encodeServerMessage(OpcodeEnum::TEXT, $message)->buff);
    }

    public static function pushFile($socket, $message)
    {
        return fwrite($socket, (new Frame())->encodeServerMessage(OpcodeEnum::BINARY, $message)->buff);
    }

    public static function notifyClose($socket)
    {
        return fwrite($socket, (new Frame())->encodeServerMessage(OpcodeEnum::OUT_CONNECT, '')->buff);
    }
}