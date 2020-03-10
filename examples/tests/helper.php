<?php
$arr = ['file[]', 'file[1]', 'file[]'];

function convert_string_field_to_array(string $key, $value)
{
    $firstKey = substr($key, 0, strpos($key, '['));
    $temp = substr(substr($key, strpos($key, '[') + 1), 0, -1);
    $subKeys = explode('][', $temp);

    $isFirst = true;
    while (count($subKeys) > 0) {
        $tempArr = [];

        $finalKey = array_pop($subKeys)?: '0';

        $finalKey = trim($finalKey, "\"");
        $finalKey = trim($finalKey, "'");

        $tempArr[$finalKey] = $value;
        $value = $tempArr;
    }

    return [$firstKey => $value];
}

function get_field_name(string $key)
{
    if (strpos($key, '[') === false || strpos($key, ']') === false) {
        return $key;
    }
    return substr($key, 0, strpos($key, '['));
}

$res1 = convert_string_field_to_array("file[3][]", [
    'filename' => '1.txt',
    'type' => 'text',
    'size' => 12
]);
$res2 = convert_string_field_to_array($field2 = "file[1][]", 'pass');
$res3 = convert_string_field_to_array("file[2][]", 'user');

var_dump(array_merge_recursive($res1, $res2, $res3));
var_dump($res1 + $res2 + $res3);