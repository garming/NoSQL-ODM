<?php
/**
 * Created by PhpStorm.
 * User: garming
 * Date: 11/19/15
 * Time: 23:01
 */

namespace NxLib\NosqlDB\Lib\Mongodb;


use NxLib\NosqlDB\Lib\DBInterface;
use \MongoDB\Driver\Manager;
use \MongoDB\Driver\Command;
use \MongoDB\Driver\ReadPreference;
use \MongoDB\Driver\BulkWrite;
use \MongoDB\Driver\WriteConcern;
use \MongoDB\Driver\Query;
use NxLib\NosqlDB\Lib\FeatureDetection;

class DB implements DBInterface
{
    /* {{{ consts & vars */
    const QUERY_FLAG_TAILABLE_CURSOR   = 0x02;
    const QUERY_FLAG_SLAVE_OKAY        = 0x04;
    const QUERY_FLAG_OPLOG_REPLY       = 0x08;
    const QUERY_FLAG_NO_CURSOR_TIMEOUT = 0x10;
    const QUERY_FLAG_AWAIT_DATA        = 0x20;
    const QUERY_FLAG_EXHAUST           = 0x40;
    const QUERY_FLAG_PARTIAL           = 0x80;


    const CURSOR_TYPE_NON_TAILABLE   = 0x00;
    const CURSOR_TYPE_TAILABLE       = self::QUERY_FLAG_TAILABLE_CURSOR;
    //self::QUERY_FLAG_TAILABLE_CURSOR | self::QUERY_FLAG_AWAIT_DATA;
    const CURSOR_TYPE_TAILABLE_AWAIT = 0x22;

    const FIND_ONE_AND_RETURN_BEFORE = 0x01;
    const FIND_ONE_AND_RETURN_AFTER  = 0x02;

    private static $_instance;

    public $config;
    private $manager;
    private $dbName;
    private $bulk;
    private $readPreference;
    private $writeConcern;
    private $collection;

    private function __clone(){}
    private function __construct($config)
    {
        $this->config = $config;
        if(!class_exists("\\MongoDB\\Driver\\Manager")){
            throw new \Exception("\n****there is no mongodb extension****\n");
        }
        if(!isset($config["host"]) || empty($config["host"])){
            throw new \Exception("\n****\$config[\"host\"] is undified****\n");
        }
        if(!isset($config["port"]) || empty($config["port"])){
            throw new \Exception("\n****\$config[\"port\"] is undified****\n");
        }
        $host = $config["host"];
        $port = $config["port"];
        $this->manager = new Manager("mongodb://{$host}:{$port}");

        if(isset($config["database"]) && !empty($config["database"])){
            $this->dbName = trim($config["database"]);
        }
        $this->bulk = new BulkWrite;
        $this->readPreference = new ReadPreference(ReadPreference::RP_PRIMARY);
        $this->writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000);

    }
    public static function connect($config)
    {
        if (!(self::$_instance instanceof self))
        {
            self::$_instance = new self($config);
        }
        if($config != self::$_instance->config){
            self::$_instance = new self($config);
        }
        return self::$_instance;
    }
    public function close()
    {
        self::$_instance = null;
    }
    public function setDbName($db_name)
    {
        $this->dbName = $db_name;
    }
    public function setCollection($collectionName)
    {
        $this->collection = $collectionName;
    }
    public function execute()
    {
        if($this->bulk->count() < 1){
            return null;
        }
        $resutl = $this->manager->executeBulkWrite($this->dbName.".".$this->collection, $this->bulk, $this->writeConcern);
        $this->bulk = new BulkWrite;
        return $resutl;
    }
    public function creatCollection($collection, array $options = [])
    {
        $collectionName = (string) trim($collection);
        $command = new Command(array('create' => $collectionName) + $options);
        return $this->manager->executeCommand($this->dbName, $command, $this->readPreference);
    }
    public function dropCollection($collectionName)
    {
        $collectionName = (string) $collectionName;
        $command = new Command(array('drop' => $collectionName));
        return $this->manager->executeCommand($this->dbName, $command, $this->readPreference);
    }
    public function isCollectionExist($collectionName)
    {
        if(empty($collectionName)){
            return false;
        }
        $list = $this->listCollection();
        return in_array($collectionName,$list);
    }
    public function listCollection(array $options = [])
    {
        $server = $this->manager->selectServer($this->readPreference);
        $command = new Command(array('listCollections' => 1) + $options);
        $cursor = $server->executeCommand($this->dbName, $command);
        $cursor->setTypeMap(array('document' => 'array'));
        $rs = [];
        foreach($cursor as $v){
            $rs[] = $v->name;
        }
        return $rs;
    }
    public function find(array $filter,array $options = [])
    {
        $options = array_merge($this->getFindOptions(), $options);
        $query = $this->_buildQuery($filter,$options);
        $cursor = $this->manager->executeQuery($this->dbName.".".$this->collection, $query, $this->readPreference);
        return $cursor;
    }
    public function findAll()
    {
        $options = $this->getFindOptions();
        $filter = [];
        $query = $this->_buildQuery($filter,$options);
        $cursor = $this->manager->executeQuery($this->dbName.".".$this->collection, $query, $this->readPreference);
        return $cursor;
    }
    public function findOne(array $filter)
    {
        $options = $this->getFindOptions();
        $options['limit'] = 1;
        $query = $this->_buildQuery($filter,$options);
        $cursor = $this->manager->executeQuery($this->dbName.".".$this->collection, $query, $this->readPreference);
        foreach($cursor as $k => $v){
            return $v;
        }
        return null;
    }
    public function insert(array $data)
    {
        $_id = $this->bulk->insert($data);
        return $_id;
    }
    public function update(array $filter,array $data,array $options = [])
    {
        if(empty($options)){
            $options = ['multi' => false, 'upsert' => false];
        }
        $this->bulk->update(
            $filter,
            $data,
            $options
        );
    }
    public function delete(array $filter,array $options = [])
    {
        $this->bulk->delete($filter,$options);
    }

    /**
     * Helper to build a Query object
     *
     * @param array $filter the query document
     * @param array $options query/protocol options
     * @return Query
     * @internal
     */
    private function _buildQuery($filter, $options)
    {
        if ($options["comment"]) {
            $options["modifiers"]['$comment'] = $options["comment"];
        }
        if ($options["maxTimeMS"]) {
            $options["modifiers"]['$maxTimeMS'] = $options["maxTimeMS"];
        }
        if ($options["sort"]) {
            $options['$orderby'] = $options["sort"];
        }

        $flags = $this->_opQueryFlags($options);
        $options["cursorFlags"] = $flags;

        $query = new Query($filter, $options);

        return $query;
    }
    /**
     * Constructs the Query Wire Protocol field 'flags' based on $options
     * provided to other helpers
     *
     * @param array $options
     * @return integer OP_QUERY Wire Protocol flags
     * @internal
     */
    private function _opQueryFlags($options)
    {
        $flags = 0;

        $flags |= $options["allowPartialResults"] ? self::QUERY_FLAG_PARTIAL : 0;
        $flags |= $options["cursorType"] ? $options["cursorType"] : 0;
        $flags |= $options["oplogReplay"] ? self::QUERY_FLAG_OPLOG_REPLY: 0;
        $flags |= $options["noCursorTimeout"] ? self::QUERY_FLAG_NO_CURSOR_TIMEOUT : 0;

        return $flags;
    }

    /**
     * Retrieves all find options with their default values.
     *
     * @return array of Collection::find() options
     */
    private function getFindOptions()
    {
        return array(
            /**
             * Get partial results from a mongos if some shards are down (instead of throwing an error).
             *
             * @see http://docs.mongodb.org/meta-driver/latest/legacy/mongodb-wire-protocol/#op-query
             */
            "allowPartialResults" => false,

            /**
             * The number of documents to return per batch.
             *
             * @see http://docs.mongodb.org/manual/reference/method/cursor.batchSize/
             */
            "batchSize" => 101,

            /**
             * Attaches a comment to the query. If $comment also exists
             * in the modifiers document, the comment field overwrites $comment.
             *
             * @see http://docs.mongodb.org/manual/reference/operator/meta/comment/
             */
            "comment" => "",

            /**
             * Indicates the type of cursor to use. This value includes both
             * the tailable and awaitData options.
             * The default is Collection::CURSOR_TYPE_NON_TAILABLE.
             *
             * @see http://docs.mongodb.org/manual/reference/operator/meta/comment/
             */
            "cursorType" => self::CURSOR_TYPE_NON_TAILABLE,

            /**
             * The maximum number of documents to return.
             *
             * @see http://docs.mongodb.org/manual/reference/method/cursor.limit/
             */
            "limit" => 0,

            /**
             * The maximum amount of time to allow the query to run. If $maxTimeMS also exists
             * in the modifiers document, the maxTimeMS field overwrites $maxTimeMS.
             *
             * @see http://docs.mongodb.org/manual/reference/operator/meta/maxTimeMS/
             */
            "maxTimeMS" => 0,

            /**
             * Meta-operators modifying the output or behavior of a query.
             *
             * @see http://docs.mongodb.org/manual/reference/operator/query-modifier/
             */
//            "modifiers" => array(),

            /**
             * The server normally times out idle cursors after an inactivity period (10 minutes)
             * to prevent excess memory use. Set this option to prevent that.
             *
             * @see http://docs.mongodb.org/meta-driver/latest/legacy/mongodb-wire-protocol/#op-query
             */
            "noCursorTimeout" => false,

            /**
             * Internal replication use only - driver should not set
             *
             * @see http://docs.mongodb.org/meta-driver/latest/legacy/mongodb-wire-protocol/#op-query
             * @internal
             */
            "oplogReplay" => false,

            /**
             * Limits the fields to return for all matching documents.
             *
             * @see http://docs.mongodb.org/manual/tutorial/project-fields-from-query-results/
             */
//            "projection" => array(),

            /**
             * The number of documents to skip before returning.
             *
             * @see http://docs.mongodb.org/manual/reference/method/cursor.skip/
             */
            "skip" => 0,

            /**
             * The order in which to return matching documents. If $orderby also exists
             * in the modifiers document, the sort field overwrites $orderby.
             *
             * @see http://docs.mongodb.org/manual/reference/method/cursor.sort/
             */
            "sort" => array(),
        );
    }
}