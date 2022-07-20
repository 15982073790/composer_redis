<?php

namespace Mrstock\Redis;

use Mrstock\Helper\Config;

/**
 * Grediscluster 操作类
 *
 */
class RedisCluster extends Redis
{

    public $constructSeedsNodes;

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $parameters
     *            array(array('127.0.0.1:80','127.0.0.1:81'))
     */
    public function __construct($parameters)
    {
        $this->s = microtime(true);
        $this->setChildClassName(__CLASS__);

        // 获取需要进行条目分片的hash key
//        if (empty(self::$hashSharding)) {
//            self::$hashSharding = Config::get('redis_hashsharding');
//        }
        if (empty($this->hashSharding)) {
            $this->hashSharding = Config::get('redis_hashsharding');
        }

        $selectSeedIndx = rand(0, count($parameters) - 1);
        $seed[] = $parameters[$selectSeedIndx];
        unset($parameters[$selectSeedIndx]);
        $parameters = array_merge_recursive($seed, $parameters);

        $this->constructSeedsNodes = $parameters;
    }

    public function __call($name, $arguMents)
    {
        $this->setSeedsNodes($this->constructSeedsNodes);

        return parent::__call($name, $arguMents);
    }


}