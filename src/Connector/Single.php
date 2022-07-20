<?php

namespace Mrstock\Redis\Connector;

use Mrstock\Helper\Config;
use Mrstock\Redis\Redis;

/**
 * Redishelper 操作类
 */
class Single extends Connector
{

    /**
     * 设置非集群redis
     * Enter description here ...
     *
     * @param unknown_type $host
     */
    public function get()
    {

        $items = $this->config[$this->host][$this->masterOrSlave];

        $seedIndx = rand(0, count($items) - 1); // 随机选取一个
        $item = $items[$seedIndx];
        $func = $item['pconnect'] ? 'pconnect' : 'connect';

        $this->handle = new Redis($item['host'], $item['port'], $func, $this->db);

        $this->setProperty();

        return $this->handle;
    }
}