<?php
namespace Bobby\ServerNetworkProtocol;

interface ParserContract
{
    public static function getOptionItems(): array;

    public function __construct(array $decodeOptions = []);

    public function input(string $buffer);

    public function decode(): array;

    public function clearBuffer();

    public function getBufferLength(): int;

    public function getBuffer(): string;
}