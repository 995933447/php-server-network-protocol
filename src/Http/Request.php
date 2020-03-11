<?php
namespace Bobby\ServerNetworkProtocol\Http;

use Bobby\ArraySpecialHelper\ArrayHelper;

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

    public $uploadedFileTempName = [];

    public function compressToEnv()
    {
        $_SERVER = $_GET = $_POST = $_REQUEST = $_FILES = [];
        $GLOBALS['HTTP_RAW_POST_DATA'] = $GLOBALS['HTTP_RAW_REQUEST_DATA'] = '';

        $this->setGlobalFiles();

        $_SERVER = $this->server;
        $_GET = $this->get;
        $GLOBALS['HTTP_RAW_POST_DATA'] = $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $this->rawContent;
        $_POST = $this->post;
        $_REQUEST = $this->request;
        $_COOKIE = $this->cookie;
    }

    protected function setGlobalFiles()
    {
        set_error_handler(function () {});

        foreach ($this->files as $name => $file) {
            ArrayHelper::convertKeyToOneDepth($file['content'], '', $contentQueries);

            foreach ($contentQueries as $query => $content) {
                ArrayHelper::queryMultidimensionalSet($file['tmp_name'], $query, '');

                if (ArrayHelper::queryMultidimensional($file['error'], $query) === UPLOAD_ERR_OK) {
                    if (!$tmpFile = tmpfile()) {
                        ArrayHelper::queryMultidimensionalSet($file['error'], $query, UPLOAD_ERR_CANT_WRITE);
                    } else {
                        $written = fwrite($tmpFile, $content);
                        if ($written === false || $written === 0) {
                            ArrayHelper::queryMultidimensionalSet($file['error'], $query, UPLOAD_ERR_CANT_WRITE);
                        } else {
                            if ($written < (int)ArrayHelper::queryMultidimensional($file['size'], $query)) {
                                ArrayHelper::queryMultidimensionalSet($file['error'], $query, UPLOAD_ERR_PARTIAL);
                            }

                            ArrayHelper::queryMultidimensionalSet($file['tmp_name'], $query, $tempName = stream_get_meta_data($tmpFile)['uri']);
                            $this->uploadedFileTempName[ArrayHelper::queryMultidimensional($file['name'], $query)] = $tempName;
                        }
                    }
                }
            }

            $_FILES[$name] = [
              'name' => $file['name'],
              'size' => $file['size'],
              'tmp_name' => $file['tmp_name'],
              'error' => $file['error']
            ];
        }

        restore_error_handler();
    }
}