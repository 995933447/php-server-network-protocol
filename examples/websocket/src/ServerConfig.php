<?php
namespace Bobby\Websocket;

class ServerConfig
{
    const SELECT_MODE = 'select';

    protected $mode = self::SELECT_MODE;
    protected $address; // 监听地址
    protected $port; // 监听端口号
    protected $workerNum;

    public function setMode(string $mode)
    {
        $this->mode = $mode;
    }

    public function setAddress(string $address)
    {
        $this->address = $address;
    }

    public function setPort(int $port)
    {
        $this->port = $port;
    }

    public function setWorkerNum(int $num)
    {
        $this->workerNum = $num;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}