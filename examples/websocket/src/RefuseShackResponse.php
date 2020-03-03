<?php
namespace Bobby\Websocket;

class RefuseShackResponse
{
    private $responseCode = 200;

    private $responseMessage = 'Upgrade failed';

    private $responseErrors = [];

    public function setErrors(array $errors, int $code = null, string $message = null)
    {
        if (!is_null($code)) $this->responseCode = $code;
        if (!is_null($message)) $this->responseMessage = $message;
        $this->responseErrors = $errors;
    }

    public function hasErrors()
    {
        return !empty($this->responseErrors);
    }

    public function response($socket)
    {
        $newHead = "HTTP/1.1 {$this->responseCode} {$this->responseMessage}\r\n";
        $newHead .= "Content-Type: application/json\r\n\r\n";
        $message = $newHead . json_encode($this->errors);
        return fwrite($socket, $message);
    }
}