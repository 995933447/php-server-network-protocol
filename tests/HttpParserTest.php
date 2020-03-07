<?php
require __DIR__ . '/../vendor/autoload.php';

class HttpParserTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider provideOptions2
     */
    public function testDecodeFormData($options)
    {
        $parser = new \Bobby\ServerNetworkProtocol\Http\Parser($options);
        $formData = <<<str
--boundary
Content-Disposition: form-data; name="MAX_FILE_SIZE"

8
--boundary
Content-Disposition: form-data; name="field"

value1
--boundary
Content-Disposition: form-data; name="file1"; filename="example.txt"
Content-Type: plain/text

value2
--boundary
Content-Disposition: form-data; name="file2"; filename="example.txt"
Content-Type: plain/text

value2222
--boundary--
str;
        $formDataLength = strlen($formData);
        $buffer = <<<str
POST /test.html HTTP/1.1
Host: example.org
Content-Length: $formDataLength
Content-Type: multipart/form-data;boundary="boundary"
str;
        $parser->input($buffer);
        $parser->decode();
        $parser->input("\r\n\r\n");
        $parser->decode();
        $parser->input($formData);
        $this->assertEquals($parser->getBuffer(), $buffer . "\r\n\r\n" . $formData);
        $requests = $parser->decode();
        foreach ($requests as $request) {
            var_dump($request->files);
        }

        foreach ($requests as $request) {
            $request->compressToEnv();
            var_dump($_FILES);
            var_dump($_POST);
        }
    }

    /**
     * @dataProvider provideOptions2
     */
    public function testDecode($options)
    {
        $parser = new \Bobby\ServerNetworkProtocol\Http\Parser($options);
        $input = "GET /hello?name=lubby HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length:0\r\nAuthorization:ertyuidfgh.fghjkfdghjkcvbn.poiu\r\n\r\n";
        $input .= "POST /hello?name=lubby HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json = json_encode(['password' => 123456, 'code' => 123])) . "\r\nAuthorization:ertyuidfgh.fghjkfdghjkcvbn.poiu\r\n\r\n" . $json;
        $input .= "PUT /hello?name=lubby HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\nAuthorization:ertyuidfgh.fghjkfdghjkcvbn.poiu\r\n\r\n";
        $parser->input($input);
        $requests = $parser->decode();
        echo "first output:\r\n";
        foreach ($requests as $request) {
            var_dump($request->header);
            var_dump($request->server);
            var_dump($request->cookie);
            var_dump($request->get);
            var_dump($request->post);
            var_dump($request->request);
        }

        echo "second output:\r\n";
        $parser->input(substr($json, 0 , 1));
        $requests = $parser->decode();
        $this->assertEquals($requests, []);
        $parser->input(substr($json, 1));
        $requests = $parser->decode();
        foreach ($requests as $request) {
            var_dump($request->header);
            var_dump($request->server);
            var_dump($request->cookie);
            var_dump($request->get);
            var_dump($request->post);
            var_dump($request->request);
        }

        echo "Third output.\r\n";
        $params = http_build_query([
            'action' => 'logout',
            'time' => time(),
            'random' => mt_rand()
        ]);
        $input = "DELETE /hello?name=lubby HTTP/1.1\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($params) . "\r\nAuthorization:ertyuidfgh.fghjkfdghjkcvbn.poiu\r\n\r\n";
        $parser->input($input);
        $parser->decode();
        $parser->input(substr($params, 0, 1));
        $parser->decode();
        $parser->input(substr($params, 1));
        $requests = $parser->decode();
        foreach ($requests as $request) {
            var_dump($request->header);
            var_dump($request->server);
            var_dump($request->cookie);
            var_dump($request->get);
            var_dump($request->post);
            var_dump($request->request);
        }
    }

    public function provideOptions2()
    {
        return [
            [
                [
                    'max_package_size' => 1024,
                    'follow_ini' => true
                ]
            ]
        ];
    }

    /**
     * @dataProvider provideOptions
     * @expectedException InvalidArgumentException
     */
    public function testConstruct($options)
    {
        new \Bobby\ServerNetworkProtocol\Http\Parser($options);
    }

    public function provideOptions()
    {
        return [
            [
                [
                    'max_package_size' => '',
                    'follow_ini' => ''
                ]
            ]
        ];
    }
}