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
$data = ['title' => 'testInsert'];
$db->setCollection($collectionName);
//insert one
$db->insert($data);
$db->execute();
//insert one done

//insert more
$data = ['title' => 'moreInsert1'];
$db->insert($data);
$data = ['title' => 'moreInsert2'];
$db->insert($data);
$db->insert($data);
$data = ['title' => 'moreInsert3'];
$db->insert($data);
$db->insert($data);
$db->insert($data);
$db->insert($data);

$db->execute();
//insert more done

//update one
$filter = $data;
//a whole document will be replaced
$db->update($filter,["newTitle" => "newTitle"]);

//add a new filed
$db->update($filter,[
    '$set' => ["newTitle" => "newTitle"]
]);

//more whole document will be replaced
$options = ['mutil' => true];
$db->update($filter,["newTitle" => "newTitleMutil"],$options);
$db->execute();


//del
$filter = ['title' => 'moreInsert2'];
//Delete all matching documents
$db->delete($filter);

$options = ['limit' => 1];
$filter = ["newTitle" => "newTitleMutil"];
//Delete first one matching documents
$db->delete($filter,$options);
$db->execute();


