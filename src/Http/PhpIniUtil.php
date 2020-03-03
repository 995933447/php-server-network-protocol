<?php
namespace Bobby\ServerNetworkProtocol\Http;

use InvalidArgumentException;

class PhpIniUtil
{
    public static function checkPostBodyExceed($post): bool
    {
        if ($postMaxSize = ini_get('post_max_size')) {
            return strlen($post) > self::transferSizeToBytes($postMaxSize);
        }
        return false;
    }

    public static function checkUploadedBodyExceed($fileData): bool
    {
        if ($uploadMaxFilesize = ini_get('upload_max_filesize')) {
            return strlen($fileData) > self::transferSizeToBytes($uploadMaxFilesize);
        }
        return false;
    }

    public static function transferSizeToBytes($size): int
    {
        if (is_numeric($size)) {
            return (int)$size;
        }

        $suffix = substr($size, -1);
        $sizeNumber = substr($size, 0, -1);

        if (!is_numeric($sizeNumber)) {
            throw new InvalidArgumentException("$size is not a valid ini size.");
        }

        if ($sizeNumber <= 0) {
            throw new InvalidArgumentException("Expect $size to be higher isn't zero or lower.");
        }

        switch (strtoupper($suffix)) {
            case 'K':
                return $sizeNumber * 1024;
            case 'M':
                return $sizeNumber * pow(1024, 2);
            case 'G':
                return $sizeNumber * pow(1024, 3);
            case 'T':
                return $sizeNumber * pow(1024, 4);
            default:
                throw new InvalidArgumentException("Unit $suffix not support.");
        }
    }
}