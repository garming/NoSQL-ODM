<?php
/**
 * Created by PhpStorm.
 * User: garming
 * Date: 11/21/15
 * Time: 16:01
 */

include "../vendor/autoload.php";
$database = include "database.php";
$db = NxLib\NosqlDB\Instance::init($database['connections']['mongodb']);
$collectionName = "myCollection";
$will_del_collection = "will_del_collection";
//add new collection
if(!$db->isCollectionExist($collectionName)){
    $db->creatCollection($collectionName);
}
//add new collection
if(!$db->isCollectionExist($will_del_collection)){
    $db->creatCollection($will_del_collection);
}
//del an exist collection
if($db->isCollectionExist($will_del_collection)){
    $db->dropCollection($will_del_collection);
}