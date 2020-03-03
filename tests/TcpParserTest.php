<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

class TcpParserTest extends TestCase
{
    /**
     * @dataProvider provideOptions4
     */
    public function testDecode4()
    {

        $parser = new \Bobby\ServerNetworkProtocol\Tcp\Parser(...func_get_args());

        $message = "Hello world\nI am a PHPer!\nYou too.";
        $rawData = pack('N', strlen($message)) . $message;
        $parser->input($rawData);
        $this->assertEquals($parser->decode(), [
            "H"
        ]);
    }

    /**
     * @dataProvider provideOptions3
     */
    public function testDecode3()
    {
        $parser = new \Bobby\ServerNetworkProtocol\Tcp\Parser(...func_get_args());

        $rawData = "Hello world\nI am a PHPer!\nYou too.";
        $parser->input($rawData);
        $this->assertEquals($parser->decode(), [
            substr("Hello world", 0, 5),
            substr("I am a PHPer!", 0, 5)
        ]);
    }

    /**
     * @dataProvider provideOptions2
     */
    public function testDecode2()
    {
        $parser = new \Bobby\ServerNetworkProtocol\Tcp\Parser(...func_get_args());

        $message = "Hello world\nI am a PHPer!\nYou too.";
        $rawData = pack('N', strlen($message)) . $message;
        $parser->input($rawData);
        $this->assertEquals($parser->decode(), [
            $message
        ]);

        $message2 = "Hello world\nI am a PHPer!\nYou too.1234";
        $rawData = pack('N', strlen($message2)) . $message2 . $rawData;
        $message3 = "Hello world\nI am a PHPer!\n";
        $message4 = "You too.1234";
        $rawData = $rawData . pack('N', strlen($message2)) . $message3;
        $parser->input($rawData);
        $this->assertEquals($parser->decode(), [
            $message2,
            $message,
        ]);
        $parser->input($message4);
        $this->assertEquals($parser->decode(), [
            $message2
        ]);

        $parser->input(pack('N', strlen($rawData)) . $rawData);
        $this->assertEquals($parser->decode(), [
            $rawData
        ]);

        $rawData1 = substr($rawData, 0, 3);
        $rawData2 = substr($rawData, 3);
        $parser->input(pack('N', strlen($rawData)) . $rawData1);
        $this->assertEquals($parser->decode(), [

        ]);

        $parser->input($rawData2);
        $this->assertEquals($parser->decode(), [
            $rawData
        ]);

        $rawData1 = substr($rawData, 0, 3);
        $rawData2 = substr($rawData, 3);
        $rawData3 = pack('N', strlen($rawData)) . $rawData;
        $parser->input(pack('N', strlen($rawData)) . $rawData1);
        $this->assertEquals($parser->decode(), [

        ]);

        $parser->input($rawData2 . $rawData3);
        $this->assertEquals($parser->decode(), [
            $rawData,
            $rawData
        ]);
    }

    /**
     * @dataProvider provideOptions
     */
    public function testDecode()
    {
        $parser = new \Bobby\ServerNetworkProtocol\Tcp\Parser(...func_get_args());

        $rawData = "Hello world\nI am a phper!\nYou too.";
        $needle = "\n";
        $this->assertEquals(true, strrpos($rawData, $needle));

        $parser->input($rawData);
        $this->assertEquals($parser->decode() ,[
            'Hello world',
            "I am a phper!",
        ]);

        $parser->input("me too!\n");
        $this->assertEquals($parser->decode() ,[
            "You too.me too!"
        ]);

        $parser->input("Hello world\nI am a phper!\nYou too.Hello world\nI am a phper!\nYou too.");
        $this->assertEquals($parser->decode() ,[
            'Hello world',
            "I am a phper!",
            "You too.Hello world",
            "I am a phper!",
        ]);
    }

    public function provideOptions3()
    {
        return [
            [
                [
                    'open_eof_split' => true,
                    'package_eof' => "\n",
                    'package_max_length' => 5,
                    'cat_exceed_package' => true
                ]
            ],
        ];
    }

    public function provideOptions4()
    {
        return [
            [
                [
                    'open_length_check' => true,
                    'package_length_type' => 'N',
                    'package_length_offset' => 0,
                    'package_body_offset' => 4,
                    'package_max_length' => 5,
                    'cat_exceed_package' => true
                ]
            ]
        ];
    }

    public function provideOptions2()
    {
        return [
            [
                [
                    'open_length_check' => true,
                    'package_length_type' => 'N',
                    'package_length_offset' => 0,
                    'package_body_offset' => 4
                ]
            ]
        ];
    }

    public function provideOptions()
    {
        return [
            [
                [
                    'open_eof_split' => true,
                    'package_eof' => "\n"
                ]
            ],
        ];
    }
}