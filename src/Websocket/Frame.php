<?php
namespace Bobby\ServerNetworkProtocol\Websocket;

class Frame
{
    const MIN_FRAME_HEX_LENGTH = 12;

    protected $finWith3Rsv;
    protected $opcode;
    protected $isMasked;
    protected $payloadLen = 0;
    protected $maskingKey = [];
    protected $payloadData = '';

    public function __construct($finWith3Rsv, $opcode, bool $isMasked, int $payloadLen, array $maskingKey, string $payloadData)
    {
        $this->finWith3Rsv = $finWith3Rsv;
        $this->opcode = $opcode;
        $this->isMasked = $isMasked;
        $this->payloadLen = $payloadLen;
        $this->maskingKey = $maskingKey;
        $this->payloadData = $payloadData;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}