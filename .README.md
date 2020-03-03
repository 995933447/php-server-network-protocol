## 按照服务器网络协议解析输入数据流的工具。可自动拆包或合包，处理数据边界，校验输入流数据格式，解析出正确的传输数据。可用于辅助PHP服务器开发。

### 解析器列表:
tcp服务器流解析器\
Bobby\ServerNetworkProtocol\Tcp\Parser\
http服务器数据流解析器\
Bobby\ServerNetworkProtocol\Http\Parser\
websocket服务器数据帧解析器\
Bobby\ServerNetworkProtocol\Websocket\Parser\
\
以上解析器均实现了Bobby\ServerNetworkProtocol\ParserContract接口，暴露以下调用方法：\
\
构造函数，传入解析选项构成解析上下文\
public function __construct(array $decodeOptions = []);\
参数列表:\
$decodeOptions 可选。解析选项

输入需要解析原生字符串:\
public function input(string $buffer);
参数列表:\
$buffer 要解析的原生字符串。

解析已输入的原生字符串,返回解析结果数组,如解析出多个合法消息则返回包含多个消息的数组。\
public function decode(): array;

清除未解析的字符串缓冲区，调用后未解析的字符串将被清除。\
public function clearBuffer();

获取剩余尚未解析的字符串长度。\
public function getBufferLength(): int;

获取剩余尚未解析的字符串。\
public function getBuffer(): string;

### 示例：
```php
$config = [
    'open_length_check' => true, // 可选,开启长度检查。开启该选项后以下选项如无特殊说明都是必选选项。
    'package_length_type' => 'N', // 长度字段打包格式，详见php pack函数。支持的格式有:c,C,s,S,v,V,n,N                                                                                      
    'package_length_offset' => 0, // 长度字段在数据包中的偏移起始位置，从0算起
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
    // output 
    /*
    array(2) {
        [0]=>
      string(34) "Hello world
    I am a PHPer!
    You too."
        [1]=>
      string(34) "Hello world
    I am a PHPer!
    You too."
    }
    */

$parser->input(substr($rawData, 10));
var_dump($parser->decode());
    // output 
    /*
    array(1) {
        [0]=>
      string(34) "Hello world
    I am a PHPer!
    You too."
    */

$config2 = [
   'open_eof_split' => true,
   'package_eof' => "\n"
];   

$parser2 = new \Bobby\ServerNetworkProtocol\Tcp\Parser($config2);

$parser2->input($message);
var_dump($parser2->decode());
    //output
    /*
    array(2) {
      [0]=>
      string(11) "Hello world"
      [1]=>
      string(13) "I am a PHPer!"
    }
    */
```