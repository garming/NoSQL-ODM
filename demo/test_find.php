<?php
/**
 * Created by PhpStorm.
 * User: garming
 * Date: 11/20/15
 * Time: 10:28
 */
include "../vendor/autoload.php";
$database = include "database.php";
$db = NxLib\NosqlDB\Instance::init($database['connections']['mongodb']);
$collectionName = "myCollection";
$db->setCollection($collectionName);

$filter = ['title' => 'testInsert'];
$rs = $db->find($filter);
foreach($rs as $document) {
    var_dump($document);
}
echo "=============find end==============\n";
$rs = $db->findOne($filter);
var_dump($rs);
echo "=============find one end==============\n";

//this will find all document in the {$collectionName}
$rs = $db->findAll();
foreach($rs as $value){
    var_dump($value);
}
