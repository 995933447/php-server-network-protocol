<?php
namespace Bobby\Websocket;

class ShackHttpRequest
{
    public $rawContent;

    public $method;

    public $uri;

    public $version;

    public $headers;

    public $body;

    public function __construct(string $rawContent)
    {
        $this->rawContent = $rawContent;
        $this->parse();
    }

    protected function parse()
    {
        $this->method = trim(substr($this->rawContent, 0, strpos($this->rawContent, "/")));
        $this->uri = trim(substr($this->rawContent, $start = strpos($this->rawContent, "/"), strpos($this->rawContent, "HTTP") - $start));
        $this->version = trim(substr($this->rawContent, $start = strpos($this->rawContent, "HTTP/") + 5, strpos($this->rawContent, "\r\n") - $start));

        list($headers, $this->body) = explode("\r\n\r\n", $this->rawContent, 2);

        $headers = explode("\r\n", $headers);
        array_shift($headers);
        foreach ($headers as $header) {
            list($headerName, $headerValue) = explode(":", $header);
            $this->headers[$headerName] = trim($headerValue);
        }

        if (isset($this->headers["Content-Type"]) && !empty($this->body)) {
            switch ($this->headers["Content-Type"]) {
                case "application/json":
                    $this->body = json_decode($this->body, true);
                    break;
                case "application/x-www-form-urlencoded":
                    $body = explode("&", $this->body);
                    foreach ($body as $key => $value) {
                        $this->body[$key] = $value;
                    }
            }
        }
    }
}
