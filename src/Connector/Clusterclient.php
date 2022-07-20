<?php

namespace Mrstock\Redis\Connector;

use Mrstock\Helper\Config;
use Mrstock\Redis\Redis;
use Mrstock\Redis\RedisClusterClient;
use Mrstock\Helper\Cache\File;

/**
 * Redishelper 操作类
 */
class Clusterclient extends Connector
{

    protected function getParam()
    {
        $fileName = 'rediscluster_' . $this->host . '_slots';
        $slots = File::get($fileName);
        if (empty($slots)) {

            $configName = ($this->masterOrSlave == "master") ? "master_groups" : "slaves";
            $param = $this->config[$this->host][$configName];

            $seedsNodes = $this->config[$this->host]['seeds_nodes'];

            $slots = RedisClusterClient::slots($seedsNodes);
            if (!empty($slots)) {
                File::set($fileName, $slots);
            }
        }
        if (!empty($slots)) {
            $param = $slots[$this->masterOrSlave];
        }

        return $param;
    }

    public function get()
    {
        $param = $this->getParam();

        $this->handle = new RedisClusterClient($param);
        $this->setProperty();

        return $this->handle;
    }
}