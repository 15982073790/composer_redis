<?php

namespace Mrstock\Redis\Connector;

use Mrstock\Helper\Config;
use Mrstock\Redis\Redis;
use Mrstock\Redis\Rediscluster;

/**
 * Redishelper 操作类
 */
class Cluster extends Connector
{


    public function get()
    {
        $parameters = $this->config[$this->host]['seeds_nodes'];
        $this->handle = new Rediscluster($parameters);
        $this->setProperty();

        return $this->handle;
    }
}