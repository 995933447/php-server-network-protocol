<?php
require __DIR__ . '/../../vendor/autoload.php';

$config = [
    'open_length_check' => true, // 可选,开启长度检查。开启该选项后以下选项如无特殊说明都是必选选项。
    'package_length_type' => 'N', // 长度字段打包格式，详见php pack函数。支持的格式有:c,C,s,S,v,V,n,N
    'package_length_offset' => 0, // 长度字段在数据包中的偏移位置，从0算起
    'package_body_offset' => 4, // 要截取的消息的偏移起始位置，即截取的消息为substr($package, $package_body_offset, $length)
    'package_max_length' => 1024, // 可选，是否限制数据包最大长度
    'cat_exceed_package' => true // 可选，如果超出数据包长度是否截取数据包，如果值为false，超出数据包最大长度的数据包解析后的结果将为null。默认为false。
];
$parser = new \Bobby\ServerNetworkProtocol\Tcp\Parser($config);

$message = "Hello world\nI am a PHPer!\nYou too.";
$rawData = pack('N', strlen($message)) . $message;
$rawData .= $rawData . (substr($rawData, 0 , 10));

$parser->input($rawData);
var_dump($parser->decode());

$config2 = [
    'open_eof_split' => true,
    'package_eof' => "\n"
];

$parser2 = new \Bobby\ServerNetworkProtocol\Tcp\Parser($config2);

$parser2->input($message);
var_dump($parser2->decode());
