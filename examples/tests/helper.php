<?php

function query_multidimensional_array_set(&$array, $query, $value)
{
    $query = substr($query, 1, -1);
    $queries = explode('][', $query);
    do_set($array, $queries, $value);
}

function do_set(&$array, $queries, $value)
{
    if (($query = $queries[0]) === '') {
        $query = is_array($array)? count($array): 0;
    }

    if (count($queries) === 1) {
        $array[$query] = $value;
    } else {
        if (!isset($array[$query])) {
            $array[$query] = null;
        }
        array_shift($queries);
        do_set($array[$query], $queries, $value);
    }
}

$arr = [];

query_multidimensional_array_set($arr, '[file][name][][][]', 888);
query_multidimensional_array_set($arr, '[file][name][0][0][]', 666);
query_multidimensional_array_set($arr, '[file][name][1]', 555);
query_multidimensional_array_set($arr, '[file][name][][]', 123);
query_multidimensional_array_set($arr, '[file][name][0][1]', 234);
query_multidimensional_array_set($arr, '[file][name][0][2]', 345);
query_multidimensional_array_set($arr, '[file][name][0][]', 567);


var_dump($arr);