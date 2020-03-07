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
        $this->uploadFiles();

        $_SERVER = $this->server;
        $_GET = $this->get;
        $GLOBALS['HTTP_RAW_POST_DATA'] = $_POST = $this->post;
        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $_REQUEST = $this->request;
        $_COOKIE = $this->cookie;
        $_FILES = $this->files;
    }

    protected function uploadFiles()
    {
        set_error_handler(function () {});

        foreach ($this->files as $name => &$file) {
            $file['tmp_name'] = '';

            if ($file['error'] === UPLOAD_ERR_OK) {
                if (!$tmpFile = tmpfile()) {
                    $file['error'] = UPLOAD_ERR_CANT_WRITE;
                } else {
                    $written = fwrite($tmpFile, $file['content']);
                    if ($written === false || $written === 0) {
                        $file['error'] = UPLOAD_ERR_CANT_WRITE;
                    } else {
                        $file['tmp_name'] = stream_get_meta_data($tmpFile)['uri'];
                        if ($written < $file['size']) {
                            $file['error'] = UPLOAD_ERR_PARTIAL;
                        }
                    }
                }
            }

            unset($file['content']);
        }

        restore_error_handler();
    }
}