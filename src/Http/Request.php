<?php
namespace Bobby\ServerNetworkProtocol\Http;

class Request
{
    public $server = [];

    public $get = [];

    public $post = [];

    public $request = [];

    public $header = [];

    public $cookie = [];

    public $files = [];

    public $rawContent;

    public $rawMessage;

    public function compressToEnv()
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        $GLOBALS['HTTP_RAW_POST_DATA'] = $_POST = $this->post;
        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $_REQUEST = $this->request;
        $_COOKIE = $this->cookie;
        $_FILES = $this->files;
    }
}