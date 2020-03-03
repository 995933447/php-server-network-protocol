<?php
namespace Bobby\ServerNetworkProtocol\Websocket;

class Frame
{
    const MIN_FRAME_HEX_LENGTH = 12;

    public $finWith3Rsv;
    public $opcode;
    public $isMasked;
    public $payloadLen = 0;
    public $maskingKey = [];
    public $payloadData = '';
}