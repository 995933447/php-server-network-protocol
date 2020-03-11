<?php
require __DIR__ . '/../../vendor/autoload.php';

$parser = new \Bobby\ServerNetworkProtocol\Http\Parser();

$body = <<<EOF
-----------------------------20896060251896012921717172737
Content-Disposition: form-data; name="file[][]"; filename="file1.txt"
Content-Type: text/plain-file1

fghjkltyuikolyuioghjk@#$%^&*sss
-----------------------------20896060251896012921717172737
Content-Disposition: form-data; name="file[2][]"; filename="file2.txt"
Content-Type: text/plain-file2

hello world!
-----------------------------20896060251896012921717172737
Content-Disposition: form-data; name="file[0][]"; filename="file3.txt"
Content-Type: text/plain-file3

I am a phper!
-----------------------------20896060251896012921717172737--
EOF;

$bodyLength = strlen($body);

$buffer = <<<str
POST /test.html HTTP/1.1
Host: example.org
Content-Length: $bodyLength
Content-Type: multipart/form-data;boundary="---------------------------20896060251896012921717172737"
str;

$parser->input($buffer);
$parser->input("\r\n\r\n");
$parser->input($body);

$requests = $parser->decode();
foreach ($requests as $request) {
    $request->compressToEnv();
    var_dump($_FILES, $_SERVER);
}