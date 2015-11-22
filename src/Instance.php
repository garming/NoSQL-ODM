<?php
/**
 * Created by PhpStorm.
 * User: garming
 * Date: 11/19/15
 * Time: 23:02
 */

namespace NxLib\NosqlDB;


use NxLib\NosqlDB\Lib\EmpFunc;

class Instance
{
    private function __construct(){}

    public function __clone(){}

    public static function init($config)
    {
        if(!isset($config['driver']) || empty($config['driver'])){
            return new EmpFunc();
        }
        $className = __NAMESPACE__.'\\Lib\\'.ucfirst($config['driver'])."\\DB";

        if(class_exists($className)){
            $obj = $className::connect($config);
            return $obj;
        }
        return new EmpFunc();
    }
}