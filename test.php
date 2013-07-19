<?php

include 'safemysqli.class.php';
$db = new SafeMySQLi();

$sql = "SELECT :one FROM n:two WHERE f = i:tre OR f = i:tre OR f = :four\n";
$args['one'] = 'hello';
$args['two'] = 'table';
$args['tre'] = 5;
$args[':four'] = 666;
echo $db->prepare($sql, $args);

$sql = "SELECT ?s FROM ?n WHERE f = ?i OR f = ?\n";
$args = array('hello', 'table', 5,666);
echo $db->prepare($sql, $args);

echo $db->prepare("SELECT c FROM t \n");
