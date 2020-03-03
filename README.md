## 按照服务器网络协议解析输入数据流的工具。可自动拆包或合包，处理数据边界，校验输入流数据格式，解析出正确的传输数据。可用于辅助PHP服务器开发。

### 支持的网络协议格式:TCP,HTTP,WEBSOCKET

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
$decodeOptions 可选。解析选项。

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

### 不同解析器之间的微小不同:
#### 构造函数：
Bobby\ServerNetworkProtocol\Tcp\Parser::__construct(array $decodeOptions = [])\
当什么选项都不设置代表不解析数据直接返回原生字符串。可设置选项:\
**open_eof_split** ***boolean*** 是否开启结束符检测,和open_length_check只能同时开启其中一个。\
**package_eof** ***string*** 消息结束符。根据该结束符检测消息边界。\
**open_length_check** ***boolean*** 开启长度检查。\
**package_length_type** ***string*** 当open_length_check为true时有效，长度字段打包格式，详见php pack函数。支持的格式有:c,C,s,S,v,V,n,N。\
**package_length_offset** ***int*** 当open_length_check为true时有效，长度字段在数据包中的偏移起始位置，从0算起。\
**package_body_offset** ***int*** 当open_length_check为true时有效，要截取的消息的偏移起始位置，即截取的消息为substr($package, $package_body_offset, $length)\
**package_max_length** ***int*** 每条消息的最大长度。仅当open_eof_split或open_length_check为true时有效。\
**cat_exceed_package** ***boolean*** 当消息超过设置的最大长度时候是否裁剪消息。默认值是false。如果值为false，当消息超出最大长度时候将返回null。

Bobby\ServerNetworkProtocol\Http\Parser::__construct(array $decodeOptions = [])\
什么都不设置代表无以下限制。可设置选项:\
**follow_ini** ***boolean*** 是否根据php.ini配置进行数据解析。
**max_package_size** ***int***  数据包的最大长度，超出该初度将抛出异常。

Bobby\ServerNetworkProtocol\Websocket\Parser::__construct(array $decodeOptions = [])\
没有可用选项设置。

#### 解析方法:
Bobby\ServerNetworkProtocol\Tcp\Parser::decode()\
返回经过根据传入构造函数选项解析出来的完整的字符串的数组。

Bobby\ServerNetworkProtocol\Http\Parser::decode()\
返回一个Bobby\ServerNetworkProtocol\Http\Request对象数组，对象里包含所有Http请求的相关信息。\
Bobby\ServerNetworkProtocol\Http\Request包含以下属性和方法:

***public $server;***
相当于$_SERVER数组。

***public $get;***
相当于$_GET数组。

***public $post;***
相当于$_POST数组。

***public $request;***
相当于$_REQUEST数组。

***public $header;***
包含HTTP头信息。

***public $cookie;***
相当于$_COOKIE数组。

***public $files;***
相当于$_FILES数组。不同的地方没有tmp_name，多了content项包含着文件内容。

***public $rawContent;***
获取原始的HTTP body内容,为字符串格式。

***public $rawMessage;***
获取原始的HTTP数据包内容，为字符串格式。

***public function compressToEnv()***\
将相关属性的值设置到$_SERVER,$_GET,$_POST,$_REQUEST,$GLOBALS以及$_FILES。

Bobby\ServerNetworkProtocol\Websocket\Parser::decode()\
返回一个Bobby\ServerNetworkProtocol\Websocket\Frame对象数组。Bobby\ServerNetworkProtocol\Websocket\Frame对象是websocket数据帧的值对象。通过$frame->payloadData可获取传入数据帧承载的数据。
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