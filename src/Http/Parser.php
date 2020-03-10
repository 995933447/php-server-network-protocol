<?php
namespace Bobby\ServerNetworkProtocol\Http;

use Bobby\ServerNetworkProtocol\PhpIniUtil;
use Bobby\ServerNetworkProtocol\ParserContract;
use InvalidArgumentException;

class Parser implements ParserContract
{
    protected $followIni = false;

    protected $maxPackageSize;

    protected $receiveBuffer = '';

    protected $allowHttpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'CONNECT', 'PATCH', 'HEAD'];

    protected static $optionItems = ['follow_ini', 'max_package_size'];

    public function __construct(array $decodeOptions = [])
    {
        $this->createDecodeContext($decodeOptions);
    }

    public static function getOptionItems(): array
    {
         return static::$optionItems;
    }

    protected function createDecodeContext($decodeOptions)
    {
         $this->followIni = $decodeOptions['follow_ini']?? false;
         $this->maxPackageSize = $decodeOptions['max_package_size']?? null;

         if (!is_bool($this->followIni)) {
             throw new InvalidArgumentException("Option follow_ini must be boolean value.");
         }
         if (!is_null($this->maxPackageSize) && !is_numeric($this->maxPackageSize)) {
             throw new InvalidArgumentException("Option max_package_size must be numeric value.");
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
        $decodedRequests = [];
        while (!is_null($request = $this->parseBuffer())) {
            $decodedRequests[] = $request;
        }
        return $decodedRequests;
    }

    protected function parseBuffer(): ?Request
    {
        $bufferLength = strlen($this->receiveBuffer);
        if (($endOfHeaders = strpos($this->receiveBuffer, "\r\n\r\n")) === false) {
            if (!is_null($this->maxPackageSize) && $bufferLength > $this->maxPackageSize) {
                $this->clearBuffer();
                throw new HttpPackageTooLongException();
            } else {
                return null;
            }
        } else if (!is_null($this->maxPackageSize) && ($endOfHeaders + 4) > $this->maxPackageSize) {
            $this->clearBuffer();
            throw new HttpPackageTooLongException();
        }

        $headersLine = substr($this->receiveBuffer, 0, $endOfHeaders);
        $this->receiveBuffer = substr($this->receiveBuffer, $endOfHeaders + 4);

        $bodyLength = 0;
        if (preg_match("/\r\nContent-Length:\\s*?(\d+)/i", $headersLine, $match)) {
            $bodyLength = (int)$match[1];
            if (!is_null($this->maxPackageSize) && ($bodyLength + $endOfHeaders + 4) > $this->maxPackageSize) {
                $this->clearBuffer();
                throw new HttpPackageTooLongException();
            }
        }

        $headers = explode("\r\n", $headersLine);
        $server = [];
        if (!preg_match('/^(?<method>[^ ]+) (?<target>[^ ]+) HTTP\/(?<version>\d\.\d)/m', $headers[0], $match)) {
            $this->clearBuffer();
            throw new HttpHeaderInvalidException('Unable to parse invalid request-line.');
        } else {
            if (!in_array($match['method'], $this->allowHttpMethods)) {
                throw new HttpHeaderInvalidException('Request method is invalid.', 400);
            }

            if ($match['version'] !== '1.1' && $match['version'] !== '1.0') {
                throw new HttpHeaderInvalidException('Received request with invalid protocol version', 400);
            }

            $server['REQUEST_METHOD'] = $match['method'];
            $server['REQUEST_URI'] = $match['target'];
            $server['SERVER_PROTOCOL'] = $match['version'];

            unset($headers[0]);
        }

        $body = '';
        if ($bodyLength > 0) {
            if (strlen($this->receiveBuffer) >= $bodyLength) {
                $body = substr($this->receiveBuffer, 0, $bodyLength);
                $this->receiveBuffer = substr($this->receiveBuffer, $bodyLength);
            } else {
                $this->receiveBuffer = $headersLine . "\r\n\r\n" . $this->receiveBuffer;
                return null;
            }
        }

        $request = new Request();
        $request->server = $server;
        $request->server['REQUEST_TIME'] = time();
        $request->server['REQUEST_TIME_FLOAT'] = microtime(true);
        $request->server['CONTENT_LENGTH'] = $bodyLength;

        $request->rawContent = $body;
        $request->rawMessage = $headersLine . "\r\n\r\n" . $body;
        unset($headersLine);

        foreach ($headers as $headerLine) {
            if (empty($headerLine)) {
                continue;
            }

            list($headerName, $headerValues) = explode(':', $headerLine, 2);
            $headerValues = trim($headerValues);

            foreach (explode(',', $headerValues) as $headerValue) {
                if ($headerValue === '') {
                    continue;
                }
                $request->header[$headerName][] = $headerValue;
            }

            $request->server[$serverName = ('HTTP_' . str_replace('-', '_', strtoupper($headerName)))] = $headerValues;

            switch ($serverName) {
                case 'HTTP_HOST':
                    if (strpos($headerValues, ':')) {
                        list($request->server['SERVER_NAME'], $request->server['SERVER_PORT']) = explode(':', $headerValues, 2);
                    } else {
                        $request->server['SERVER_NAME'] = $headerValues;
                        $request->server['SERVER_PORT'] = (string)80;
                    }
                    break;
                case 'HTTP_COOKIE':
                    parse_str(str_replace(';', '&', $headerValues), $request->cookie);
                    $request->header[$headerName] = $request->cookie;
                    break;
                case 'HTTP_CONTENT_TYPE':
                    if ($valuePosition = strpos($headerValues, ';')) {
                        $request->server['CONTENT_TYPE'] = substr($headerValues, 0, $valuePosition);
                    } else {
                        $request->server['CONTENT_TYPE'] = $headerValues;
                    }
            }
        }

        $this->parseBody($body, $request);

        $request->server['QUERY_STRING'] = parse_url($request->server['REQUEST_URI'], PHP_URL_QUERY);
        if ($request->server['QUERY_STRING']) {
            parse_str($request->server['QUERY_STRING'], $request->get);
        } else {
            $request->server['QUERY_STRING'] = '';
        }

        $request->request = array_merge($request->request, $request->get, $request->post);

        return $request;
    }

    protected function parseBody(string $body, Request $request)
    {
        if (empty($body)) {
            return;
        }

        if (!isset($request->server['REQUEST_METHOD'])) {
            throw new InvalidArgumentException('You need to parse http method first.');
        }

        if ($this->followIni && $request->server['REQUEST_METHOD'] === 'POST' && PhpIniUtil::checkPostBodyExceed($body)) {
            throw new HttpPackageTooLongException("Post body size exceed.");
        }

        switch ($request->server['CONTENT_TYPE']) {
            case 'application/x-www-form-urlencoded':
                $parsedBody = [];
                parse_str($body, $parsedBody);
                if ($request->server['REQUEST_METHOD'] === 'POST') {
                    $request->post = $parsedBody;
                } else {
                    $request->request = $parsedBody;
                }
                break;
            case 'application/json':
                $parsedBody = json_decode($body, true);
                if ($request->server['REQUEST_METHOD'] === 'POST') {
                    $request->post = $parsedBody;
                } else {
                    $request->request = $parsedBody;
                }
                break;
            case 'multipart/form-data':
                $this->parseFormData($body, $request);
        }
    }

    protected function parseFormData(string $body, Request $request)
    {
        if (empty($body)) {
            return;
        }

        if (!isset($request->server['REQUEST_METHOD'])) {
            throw new InvalidArgumentException('You need to parse http method first.');
        } else if ($request->server['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!preg_match('/boundary="*?([^"]+)"*?/', $request->server['HTTP_CONTENT_TYPE'], $match)) {
            throw new InvalidArgumentException('Http header has not boundary name.');

        }
        $body = substr($body, 0, -1 * (strlen($boundary = $match[1]) + 4));
        $bodyComponents = explode("--" . $boundary . "\r\n", $body);

        $htmlMaxFileSize = null;
        $files = [];
        foreach ($bodyComponents as $bodyComponent) {
            if (empty($bodyComponent)) {
                continue;
            }

            list($bufferHeaders, $bufferValue) = explode("\r\n\r\n", $bodyComponent, 2);
            $bufferValue = rtrim($bufferValue, "\r\n");

            if ($this->followIni && PhpIniUtil::checkPostBodyExceed($bufferValue)) {
                throw new HttpPackageTooLongException("Post body size exceed.");
            }

            $parsedBufferHeader = [];
            foreach (explode("\r\n", $bufferHeaders) as $bufferHeader) {
                if (empty($bufferHeader)) {
                    continue;
                }

                list($bufferHeaderKey, $bufferHeaderValue) = explode(':', $bufferHeader, 2);
                $bufferHeaderValue = trim($bufferHeaderValue);

                switch (strtolower($bufferHeaderKey)) {
                    case 'content-disposition':
                        if (preg_match('/name="(?<field>.+?)"/', $bufferHeaderValue, $match)) {
                            $parsedBufferHeader['name'] = $match['field'];
                        }
                        if (preg_match('/filename="(?<filename>.+?)"/', $bufferHeaderValue, $match)) {
                            $parsedBufferHeader['filename'] = $match['filename'];
                        }
                        break;
                    case 'content-type':
                        if ($contentTypePosition = strpos($bufferHeaderValue, ';')) {
                            $parsedBufferHeader['type'] = substr($bufferHeaderValue, 0, $contentTypePosition);
                        } else {
                            $parsedBufferHeader['type'] = $bufferHeaderValue;
                        }
                        break;
                }
            }

            if (isset($parsedBufferHeader['filename'])) {
                $files[$parsedBufferHeader['name']] = [
                    'name' => $parsedBufferHeader['filename'],
                    'size' => strlen($bufferValue),
                    'content' => trim($bufferValue)
                ];

                if ($this->followIni && PhpIniUtil::checkUploadedBodyExceed($bufferValue)) {
                    $files[$parsedBufferHeader['name']]['error'] = UPLOAD_ERR_INI_SIZE;
                } else {
                    $files[$parsedBufferHeader['name']]['error'] = UPLOAD_ERR_OK;
                }

                if (isset($parsedBufferHeader['type'])) {
                    $files[$parsedBufferHeader['name']]['type'] = $parsedBufferHeader['type'];
                }
            } else {
                if ($parsedBufferHeader['name'] === 'MAX_FILE_SIZE') {
                    $htmlMaxFileSize = (int)trim($bufferValue);
                }

                $request->post[$parsedBufferHeader['name']] = $bufferValue;
            }
        }

        if (!is_null($htmlMaxFileSize)) {
            foreach ($files as &$file) {
                if ($file['size'] > $htmlMaxFileSize && $file['error'] === UPLOAD_ERR_OK) {
                    $file['error'] = UPLOAD_ERR_FORM_SIZE;
                }
            }
        }

        $request->files = array_merge($request->files, $files);
    }
}