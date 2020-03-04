<?php
namespace Bobby\ServerNetworkProtocol\Websocket;

use Bobby\ServerNetworkProtocol\ParserContract;

class Parser implements ParserContract
{
    protected $receiveBuffer = '';

    protected $segmentPayloads = '';

    public function __construct(array $decodeOptions = [])
    {
    }

    public static function getOptionItems(): array
    {
        return [];
    }

    public function input(string $buffer)
    {
        $this->receiveBuffer .= $buffer;
    }

    public function decode(): array
    {
        $frames = [];
        while (!is_null($frame = $this->parseBuffer())) {
            $frames[] = $frame;
        }
        return $frames;
    }

    protected function parseBuffer(): ?Frame
    {
        $message = unpack('H*', $this->receiveBuffer)[1];

        if (($messageLen = strlen($message)) < Frame::MIN_FRAME_HEX_LENGTH) {
            return null;
        }

        $finWith3Rsv = substr($message, 0, 1);
        $validFinWith3RsvEnum = (new \ReflectionClass(new FinWith3RsvEnum()))->getConstants();
        if (!in_array($finWith3Rsv, array_values($validFinWith3RsvEnum))) {
            throw new InvalidFrameException();
        }

        $opcode = substr($message, 1, 1);
        $validOpcode = (new \ReflectionClass(new OpcodeEnum()))->getConstants();
        if (!in_array($opcode, array_values($validOpcode))) {
            throw new InvalidFrameException();
        }

        $isMasked = true; // 客户端请求服务端需要设置屏蔽码， buff mask必须位1个Bit的1,反之不需要设置
        $payloadLenDescBytes = 0;
        $payloadLen = 0;
        $maskingKey = [];
        $payloadData = '';

        if (in_array($opcode, [OpcodeEnum::TEXT, OpcodeEnum::BINARY, OpcodeEnum::SEGMENT])) {
            if (($payloadLen = (hexdec(substr($message, 2, 2)) & 127)) < 126) {
                $maskingKeyStart = 1+ 1 + 2;
            } else {
                switch ($payloadLen) {
                    case 126:
                        $payloadLenDescBytes = 16 / 4;
                        break;
                    default:
                        $payloadLenDescBytes = 64 / 4;
                }

                if ($messageLen < (Frame::MIN_FRAME_HEX_LENGTH + $payloadLenDescBytes)) {
                    return null;
                }

                $payloadLen = hexdec(substr($message, 4, $payloadLenDescBytes)); // 如果payload len为126 则取后面16位表示payload data的长度,如果127则用后面64位表示
                $maskingKeyStart = 1 + 1 + 2 + $payloadLenDescBytes; // FIN,RSV1,RSV2,Rsv3 + OPCODE+ MASK,PAYLOAD LEN + EXTEND PAYLOAD LEN
            }

            if ($messageLen < ($completeFrameLen = Frame::MIN_FRAME_HEX_LENGTH + $payloadLenDescBytes + $payloadLen * 2)) {
                return null;
            }

            $message = substr($message, 0, $completeFrameLen);
            $this->receiveBuffer = pack('H*', substr($message, $completeFrameLen));

            for ($i = 0; $i < 4; $i++) {
                $maskingKey[] = hexdec(substr($message, $maskingKeyStart + $i * 2, 2)); // 截取32位屏蔽码
            }

            for($payloadDataStart = $maskingKeyStart + 32 / 4, $n = 0; $payloadDataStart < strlen($message); $payloadDataStart += 2, $n++) {
                $payloadData .= chr(hexdec(substr($message, $payloadDataStart, 2)) ^ $maskingKey[$n % 4]);
            }

            if ($finWith3Rsv == FinWith3RsvEnum::NO_FINISH || $opcode == OpcodeEnum::SEGMENT) {
                $lastSegmentPayloadData = $this->segmentPayloads;

                if ($finWith3Rsv == FinWith3RsvEnum::NO_FINISH) {
                    $this->segmentPayloads = $lastSegmentPayloadData . $this->segmentPayloads;
                    return null;
                }

                if ($opcode == OpcodeEnum::SEGMENT) {
                    $this->segmentPayloads = '';
                    $payloadData = $lastSegmentPayloadData . $payloadData;
                }
            }
        }

        return new Frame($finWith3Rsv, $opcode, $isMasked, $payloadLen, $maskingKey, $payloadData);
    }

    public function getBuffer(): string
    {
        return $this->receiveBuffer;
    }

    public function getBufferLength(): int
    {
        return strlen($this->receiveBuffer);
    }

    public function clearBuffer()
    {
        $this->receiveBuffer = '';
        $this->segmentPayloads = '';
    }
}