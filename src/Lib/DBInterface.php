<?php
/**
 * Created by PhpStorm.
 * User: garming
 * Date: 11/20/15
 * Time: 10:22
 */

namespace NxLib\NosqlDB\Lib;


interface DBInterface
{
    public static function connect($config);

    public function close();
    public function setDbName($db_name);
    public function setCollection($collectionName);
    public function execute();
    public function creatCollection($collection, array $options = []);
    public function dropCollection($collectionName);
    public function isCollectionExist($collectionName);
    public function listCollection(array $options = []);
    public function find(array $filter,array $options = []);
    public function findAll();
    public function findOne(array $filter);
    public function insert(array $data);
    public function update(array $filter,array $data,array $options = []);
    public function delete(array $filter,array $options = []);
}