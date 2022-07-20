<?php

namespace Mrstock\Redis;

use Mrstock\Helper\Crc16;

/**
 * GRedis 操作类
 *
 */
class RedisClusterClient extends Redis
{

    private $groupPre = 'redis_connection_';

    private $redisConnectionsConfig;

    private $clusterGroups;

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $parameters
     *            array(array('host'=>'192.168.10.243','port'=>63791,'pconnect'=>0),array('host'=>'192.168.10.243','port'=>7379,'pconnect'=>0)),
     *            array(array('host'=>'192.168.10.243','port'=>63791,'pconnect'=>0),array('host'=>'192.168.10.243','port'=>7379,'pconnect'=>0)),
     *            array(array('host'=>'192.168.10.243','port'=>63791,'pconnect'=>0),array('host'=>'192.168.10.243','port'=>7379,'pconnect'=>0))
     */
    public function __construct($parameters)
    {
        $this->setChildClassName(__CLASS__);

        $this->clusterGroups = array();
        foreach ($parameters as $k => $v) {
            $this->clusterGroups[] = $k;
            $items = $v;
            $seedIndx = $this->randWeight($items); // 随机选取一个
            $item = $items[$seedIndx];

            $ip = $item['host'];
            $port = $item['port'];
            $func = $item['pconnect'];
            $weight = isset($item['weight']) ? $item['weight'] : 1;
            $this->preidsToRedis($ip, $port, $func, $weight, $k);
        }
    }

    public static function slots($seedsNodes)
    {
        $result = [];
        $re = null;

        try {
            $handle = self::clusterHandle($seedsNodes);
            $re = $handle->eval("return redis.call('cluster','slots')");
        } catch (\Exception $ex) {
            $re = null;
            parent::checkResult($re, __METHOD__, func_get_args());
        }

        if (!empty($re)) {
            $masters = [];
            $slaves = [];
            foreach ($re as $slots) {
                $key = $slots[1];
                $tmpMasters = $slots[2];
                unset($slots[0]);
                unset($slots[1]);

                $master = [];
                $master['host'] = $tmpMasters[0];
                $master['port'] = $tmpMasters[1];
                $master['pconnect'] = 1;
                $master['db'] = 0;
                $masters[$key][] = $master;
                if ($handle->haveReadonly()) {
                    unset($slots[2]);
                } else {
                    $slots = [];
                    $slots[] = $tmpMasters;
                }

                foreach ($slots as $slot) {
                    $slave = [];
                    $slave['host'] = $slot[0];
                    $slave['port'] = $slot[1];
                    $slave['pconnect'] = 1;
                    $slave['db'] = 0;
                    $slaves[$key][] = $slave;
                }
            }

            $result['master'] = $masters;
            $result['slave'] = $slaves;
        }
        return $result;
    }

    /**
     * 随机获取一个种子实例
     *
     * @param unknown $seedsNodes
     * @return Gredis
     */
    private static function clusterHandle($seedsNodes)
    {
        $selectSeedIndx = rand(0, count($seedsNodes) - 1);
        $seed = $seedsNodes[$selectSeedIndx];
        $host = explode(':', $seed);

        return new Redis($host[0], $host[1], 'pconnect', 0);
    }

    /**
     * 单参数使用
     * Enter description here ...
     *
     * @param unknown_type $name
     * @param unknown_type $arguments
     */
    public function __call($name, $arguMents)
    {
        $arguMent = $arguMents[0];
        $this->setClient($arguMent);

        return parent::__call($name, $arguMents);
    }

    /**
     *
     * 多keys 多值批量操作
     *
     * @param unknown_type $data
     * @param unknown_type $pipe
     *            true 没有事务 false 事务
     *            事务
     *            开始事务。
     *            命令入队。
     *            执行事务
     *            [
     *            'testmhset1'=>['call'=>'hset','args'=>[1,1],
     *            'testmhset2'=>['call'=>'set','args'=>[1]],
     *            'testmhset3'=>['call'=>'zadd','args'=>[1,2]],
     *            ]
     */
    public function gmulti($data, $pipe = false)
    {
        $keys = array_keys($data);

        $return = array();
        $groups = $this->groupKeys($keys);

        foreach ($groups as $k => $group) {
            $groupData = array();
            foreach ($group as $v) {
                $groupData[$v] = $data[$v];
            }
            $this->setClientBygroup($k);

            $result = parent::gmulti($groupData, $pipe);

            if (is_array($result)) {
                $return = array_merge($return, $result);
            }
        }

        return $return;
    }

    /**
     *
     * 对key进行分组
     *
     * @param unknown_type $keys
     */
    private function groupKeys($keys)
    {
        $list = array();
        foreach ($keys as $key) {
            $slot = $this->getGroupByKey($key);
            $list[$slot][] = $key;
        }
        return $list;
    }

    /**
     * 按照权重获取
     * Enter description here ...
     *
     * @param unknown_type $parameters
     */
    private function randWeight($items)
    {
        $weights = array();
        foreach ($items as $v) {
            if (isset($v['weight'])) {
                $weights[] = $v['weight'];
            } else {
                $weights[] = 1; // 如果没有配置权重 则权重为1
            }
        }

        $roll = rand(1, array_sum($weights));

        $_tmpW = 0;
        foreach ($weights as $k => $v) {
            $min = $_tmpW;
            $_tmpW += $v;
            $max = $_tmpW;

            if ($roll > $min && $roll <= $max) {
                return $k;
            }
        }
    }

    /**
     * 把集群配置 转为单redis配置
     * Enter description here ...
     *
     * @param unknown_type $ip
     * @param unknown_type $port
     * @param unknown_type $num
     */
    private function preidsToRedis($ip, $port, $func, $weight, $group)
    {
        $item = array();
        $item['ip'] = $ip;
        $item['port'] = $port;
        $item['db'] = 0;
        $item['pconnect'] = $func;
        $item['weight'] = $weight;
        $connectionName = $this->groupPre . $group;

        if (empty($this->redisConnectionsConfig) || !array_key_exists($connectionName, $this->redisConnectionsConfig)) {
            $this->redisConnectionsConfig[$connectionName] = $item;
        }
    }

    /**
     * predis 设置client
     * Enter description here ...
     *
     * @param unknown_type $keyname
     */
    private function setClient($keyName)
    {
        $group = $this->getGroupByKey($keyName);
        $this->setClientByGroup($group);
    }

    private function setClientByGroup($group)
    {
        $connectionName = $this->groupPre . $group;

        $this->client['connection_name'] = $connectionName;
        $this->client['connections_config'] = $this->redisConnectionsConfig;

        $config = $this->redisConnectionsConfig[$connectionName];
        $func = $config['pconnect'] ? 'pconnect' : 'connect';

        parent::setClientId($config['ip'], $config['port'], $func, 0, $connectionName);
    }

    private function getGroupByKey($key)
    {
        $slot = $this->getSlotByKey($key);
        sort($this->clusterGroups);
        foreach ($this->clusterGroups as $v) {
            if ($slot <= $v) {
                return $v;
            }
        }
    }

    /**
     * 根据key 获取相应的桶
     * Enter description here ...
     *
     * @param unknown_type $key
     */
    private function getSlotByKey($key)
    {
        return abs(Crc16::hash($key)) % 16384;
    }
}