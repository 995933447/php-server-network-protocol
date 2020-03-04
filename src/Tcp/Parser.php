<?php
namespace Bobby\ServerNetworkProtocol\Tcp;

use Bobby\ServerNetworkProtocol\ParserContract;
use InvalidArgumentException;

class Parser implements ParserContract
{
    const NONE_CHECK_TYPE = 0;

    const EOF_CHECK_TYPE = 1;

    const LENGTH_CHECK_TYPE = 2;

    protected $checkType = self::NONE_CHECK_TYPE;

    protected $packageEof;

    protected $packageLengthType;

    protected $packageLengthLength;

    protected $packageLengthOffset;

    protected $packageBodyOffset;

    protected $packageMaxLength;

    protected $cutExceedPackage = false;

    protected $receiveBuffer = '';

    protected static $optionItems = [
        'open_eof_split',
        'open_length_check',
        'package_eof',
        'package_length_type',
        'package_length_offset',
        'package_body_offset',
        'package_max_length',
        'cat_exceed_package'
    ];

    public function __construct(array $decodeOptions = [])
    {
        $this->setDecodeContext($decodeOptions);
    }

    public static function getOptionItems(): array
    {
        return static::$optionItems;
    }

    protected function setDecodeContext(array $decodeOptions)
    {
        if (!empty($decodeOptions)) {
            $openEofSplit = $decodeOptions['open_eof_split']?? false;
            $openLengthCheck = $decodeOptions['open_length_check']?? false;

            if (!is_bool($openEofSplit) || !is_bool($openLengthCheck)) {
                throw new InvalidArgumentException("open_eof_split or open_length_check must be boolean.");
            }

            if ($openEofSplit && $openLengthCheck) {
                throw new InvalidArgumentException("Option open_eof_split and open_length_check both cannot be turned on at the same time.");
            }

            if ($openEofSplit) {
                $this->checkType = static::EOF_CHECK_TYPE;
                $this->packageEof = $decodeOptions['package_eof']?? '';
                if (empty($this->packageEof)) {
                    throw new InvalidArgumentException("Option package_eof must be not empty if open_eof_split is true.");
                }
            } else if ($openLengthCheck) {
                $this->checkType = static::LENGTH_CHECK_TYPE;
                $this->packageLengthType = $decodeOptions['package_length_type']?? null;
                $this->packageLengthOffset = $decodeOptions['package_length_offset']?? null;
                $this->packageBodyOffset = $decodeOptions['package_body_offset']?? null;

                if (is_null($this->packageLengthType) || is_null($this->packageLengthOffset) || is_null($this->packageBodyOffset)) {
                    throw new InvalidArgumentException("package_length_type and package_length_offset and package_body_offset " .
                        "options must be not empty if open_length_check is true.");
                }

                $this->packageLengthOffset = (int)$this->packageLengthOffset;
                $this->packageBodyOffset = (int)$this->packageBodyOffset;

                switch ($this->packageLengthType) {
                    case 'c':
                    case 'C':
                        $this->packageLengthLength = 1;
                        break;
                    case 's':
                    case 'v':
                    case 'S':
                        $this->packageLengthLength = 2;
                        break;
                    case 'n':
                    case 'V':
                    case 'N':
                        $this->packageLengthLength = 4;
                        break;
                    default:
                        throw new InvalidArgumentException('Options package_length_type only support(c,C,s,S,v,V,n,N).');
                }
            }

            $this->packageMaxLength = $decodeOptions['package_max_length']?? null;
            if (!is_null($this->packageMaxLength)) {
                $this->packageMaxLength = (int)$this->packageMaxLength;

                $catExceedPackage = $decodeOptions['cat_exceed_package']?? false;
                if (!is_bool($catExceedPackage)) {
                    throw new InvalidArgumentException("Option cat_exceed_package must be boolean.");
                }
                $this->cutExceedPackage = $catExceedPackage;
            }
        }
    }

    public function input(string $buffer)
    {
        $this->receiveBuffer .= $buffer;
    }

    public function clearBuffer()
    {
        $this->receiveBuffer = '';
    }

    public function getBufferLength(): int
    {
        return strlen($this->receiveBuffer);
    }

    public function getBuffer(): string
    {
        return $this->receiveBuffer;
    }

    public function decode(): array
    {
        switch ($this->checkType) {
            case static::EOF_CHECK_TYPE:
                return $this->useEofCheckToParse();
            case static::LENGTH_CHECK_TYPE:
                return $this->useLengthCheckToParse();
            default:
                return [$this->useNoneToParse()];
        }
    }

    protected function useNoneToParse(): string
    {
        return $this->receiveBuffer;
    }

    protected function useEofCheckToParse(): array
    {
        if (($lastEofPosition = strrpos($this->receiveBuffer, $this->packageEof)) === false) {
            return [];
        } else {
            $readyOkData = substr($this->receiveBuffer, 0, $lastEofPosition + ($eofLength = strlen($this->packageEof)));
            $this->receiveBuffer = substr($this->receiveBuffer, $lastEofPosition + ($eofLength = strlen($this->packageEof)));

            $decodesMessages = explode($this->packageEof, rtrim($readyOkData, $this->packageEof), substr_count($readyOkData, $this->packageEof));

            if ($this->packageMaxLength > 0) {
                foreach ($decodesMessages as $index => $decodesMessage) {
                    if (strlen($decodesMessage) > $this->packageMaxLength) {
                        if ($this->cutExceedPackage) {
                            $decodesMessages[$index] = substr($decodesMessage, 0, $this->packageMaxLength);
                        } else {
                            $decodesMessages[$index] = false;
                        }
                    }
                }
            }

            return $decodesMessages;
        }
    }

    protected function useLengthCheckToParse(): array
    {
        $decodedMessages = [];
        $canGetPackageLengthMinBufferLength = $this->packageLengthOffset + $this->packageLengthLength;
        while (1) {
            if (($bufferLength = strlen($this->receiveBuffer)) >= $canGetPackageLengthMinBufferLength) {
                $packageLength = unpack($this->packageLengthType, substr($this->receiveBuffer, $this->packageLengthOffset, $this->packageLengthLength))[1];

                if ($bufferLength >=  ($needBufferLength = $packageLength + $this->packageBodyOffset)) {
                    $bufferSegment = substr($this->receiveBuffer, 0, $needBufferLength);
                    $this->receiveBuffer = substr($this->receiveBuffer, $needBufferLength);

                    if ($this->packageMaxLength > 0 && $needBufferLength > $this->packageMaxLength) {
                        if ($this->cutExceedPackage) {
                            $bufferSegment = substr($bufferSegment, 0, $this->packageMaxLength);
                        } else {
                            $decodedMessages[] = false;
                            continue;
                        }
                    }

                    $decodedMessages[] = substr($bufferSegment, $this->packageBodyOffset, $packageLength);
                } else {
                    break;
                }
            } else {
                break;
            }
        }

        return $decodedMessages;
    }
}