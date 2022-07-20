<?php

namespace Mrstock\Redis;

use Mrstock\Mjc\Facade\Log;
use Mrstock\Mjc\Log\LogLevel;
use Mrstock\Mjc\Container;
use Mrstock\Mjc\Facade\Hook;

/**
 * Gredis 操作类
 */
class Redis
{

    public $errorMsg = '';

    public $client;

    public $s;

    // 是否需要发送readonly
    public $needReadonly = 0;

    public $hashSharding;

    public $prefix;

    private $indexPre = 'i';

    // hash分区索引前缀
    private $indexFactor = 5;

    // hash分区 右取长度因子
    private $childName = 'Redis';

    private $ip;

    private $port;

    private $func;

    private $db;

    private $group;

    private $seedsNodes;

    private $clientId;

    private static $clientCache;

    private $notRedisRecord = array('SELECT' => 1, 'LLEN' => 1, 'BRPOP' => 1);

    protected $app;

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $parameters
     *            array(array('127.0.0.1:80','127.0.0.1:81'))
     */
    public function __construct($ip, $port, $func, $db = 0, $group = '')
    {
        $this->app = Container::get("app");
        $this->s = microtime(true);

        $this->setClientId($ip, $port, $func, $db, $group);

        $this->init();
    }

    public function setClientId($ip, $port, $func, $db = 0, $group = '')
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->func = $func;
        $this->db = $db;
        $this->group = $group;

        $this->seedsNodes = '';

        $this->clientId = $this->ip . ':' . $this->port . ':' . $this->func . ':' . $this->db . ':' . $this->group;
    }

    public function setSeedsNodes($seedsNodes)
    {
        $this->seedsNodes = $seedsNodes;
        $this->clientId = implode(',', $seedsNodes);

        return true;
    }

    public function init()
    {
        // 连接创建时间
        $createTime = 0;
        if (isset(self::$clientCache['createtime'][$this->clientId])) {
            $createTime = intval(self::$clientCache['createtime'][$this->clientId]);
        }

        // 如果大于链接 默认持续时间 则重新建立链接
        if ((time() - $createTime) < 20) {
            if (isset(self::$clientCache[$this->clientId]) && self::$clientCache[$this->clientId] != null) {

                try {
                    // cluster ping 带参数
                    $enble = self::$clientCache[$this->clientId]->ping();
                } catch (\Exception $ex) {
                    $enble = false;
                    $this->errorMsg = 'ping:' . $ex;
                }

                if ($enble) {
                    $this->client['handle'] = self::$clientCache[$this->clientId];
                    return;
                }

                $this->client['handle'] = null;
                unset($this->client['handle']);

                self::$clientCache[$this->clientId] = null;
                unset(self::$clientCache[$this->clientId]);
            }
        }


        $re = $this->tryInit();
        $tryNum = 1; // 重试次数
        while (!$re && $tryNum < 2) {
            $re = $this->tryInit();
            $tryNum++;
        }

        // 连接创建时间
        self::$clientCache['createtime'][$this->clientId] = time();

        $this->checkResult($re, __METHOD__ . ':' . $tryNum, func_get_args());

        if (isset(self::$clientCache[$this->clientId])) {
            self::$clientCache[$this->clientId]->close();
        }

        self::$clientCache[$this->clientId] = $re;
        $this->client['handle'] = $re;

        return true;
    }

    private function tryInit()
    {
        if (empty($this->seedsNodes)) {
            return $this->tryInitSingle();
        } else {
            return $this->tryInitCluster();
        }
    }

    private function tryInitSingle()
    {
        $ip = $this->ip;
        $port = $this->port;
        $func = $this->func;
        $db = $this->db;

        $re = new \Redis();
        try {
            $enble = $re->$func($ip, $port, 2);
            if (!empty(getenv('vendor_redis_master_key'))) {
                $re->auth(getenv('vendor_redis_master_key'));
            }
        } catch (\Exception $ex) {
            $this->errorMsg = $func . ':' . $ex;
            $enble = false;
        }
        if ($enble) {
            if ($this->needReadonly) {
                if (method_exists($re, 'readonly')) {
                    try {
                        $enble = $re->readonly();
                    } catch (\Exception $ex) {
                        $enble = false;
                        $this->errorMsg = 'readonly:' . $ex;
                    }
                }
            }

            if ($enble) {
                try {
                    $enble = $re->select(intval($db));
                } catch (\Exception $ex) {
                    $enble = false;
                    $this->errorMsg = 'select:' . $ex;
                }
            }

            if (empty($enble)) {
                $re = null;
            }
        } else {
            $re = null;
        }
        return $re;
    }

    private function tryInitCluster()
    {
        try {
            $re = new \RedisCluster(NUll, $this->seedsNodes, 1, 1);
        } catch (\Exception $ex) {
            $this->errorMsg = 'tryCluster:' . $ex;
            $re = null;
        }
        return $re;
    }

    public function haveReadonly()
    {
        if (isset($this->client['handle']) && !is_null(@$this->client['handle'])) {
            if (method_exists($this->client['handle'], 'readonly')) {
                return true;
            }
        }
        return false;
    }

    public function setChildClassName($name)
    {
        $this->childName = $name;
        return true;
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

        $this->init();

        $debug_state = true;
        $debug_result = null;

        if (isset($this->client['handle']) && !is_null(@$this->client['handle'])) {
            try {
                $this->s = microtime(true);

                $re = call_user_func_array(array(@$this->client['handle'], $name), $arguMents);
            } catch (\Exception $ex) {
                $this->errorMsg = 'call ' . $ex;
                $re = null;
                $debug_state = false;
                $debug_result = __LINE__;
            }
            $this->checkResult($re, __METHOD__, func_get_args());
        } else {
            $debug_state = false;
            $debug_result = __LINE__;

        }
        Hook::listen('debug_record', ['type' => 'Redis', 'link' => $this->clientId, 'command' => $arguMents, 'starttime' => $this->s, 'result' => $debug_result, 'state' => $debug_state]);

        return $re;
    }

    public function ghset($keyName, $field, $value)
    {
        list ($keyName, $field) = $this->getHashKey($keyName, $field);
        return $this->hset($keyName, $field, $value);
    }

    public function ghget($keyName, $field)
    {
        list ($keyName, $field) = $this->getHashKey($keyName, $field);
        return $this->hget($keyName, $field);
    }

    public function ghdel($keyName, $field)
    {
        list ($keyName, $field) = $this->getHashKey($keyName, $field);
        return $this->hdel($keyName, $field);
    }

    public function ghmset($keyName, $list)
    {
        // 是否分片
        if (!in_array($keyName, $this->hashSharding)) {
            return $this->hmset($keyName, $list);
        }
        $result = array();
        foreach ($list as $k => $v) {
            list ($keyName, $field) = $this->getHashKey($keyName, $k);
            $result[] = $this->hset($keyName, $field, $v);
        }
        return $result;
    }

    public function ghmget($keyName, $fields)
    {
        // 是否分片
        if (!in_array($keyName, $this->hashSharding)) {
            return $this->hmget($keyName, $fields);
        }
        $result = array();
        foreach ($fields as $v) {
            list ($keyName, $field) = $this->getHashKey($keyName, $v);
            $result[] = $this->hget($keyName, $field);
        }
        return $result;
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
        $this->init();
        $this->s = microtime(true);
        if (!isset($this->client['handle']) || is_null($this->client['handle']) || !is_array($data) || count($data) == 0) {
            return array();
        }

        if ($pipe) {
            $pipe = $this->client['handle']->multi(\Redis::PIPELINE);
        } else {
            $pipe = $this->client['handle']->multi();
        }


        foreach ($data as $k => $item) {
            $name = $item['call'];
            $arguMents = $item['args'];

            array_unshift($arguMents, $k);


            call_user_func_array(array($pipe, $name), $arguMents);
        }
        $result = $pipe->exec();
        $newRew = [];

        $prefixLen = strlen($this->prefix);

        $i = 0;
        foreach ($data as $k => $item) {
            // 只替换一次前缀
            $k = substr_replace($k, '', 0, $prefixLen);
            $newRew[$k] = $result[$i];
            $i++;
        }
        $this->checkResult($newRew, __METHOD__, func_get_args());
        return $newRew;
    }

    public function checkResult($re, $method, $args)
    {
        $classExport = var_export($this->childName, true);
        $methodExport = var_export($method, true);
        $argsExport = var_export($args, true);
        $result = gettype($re) . '---' . var_export($re, true);
        $client = print_r($this->client, true);
        $err = '';
        if (!empty($this->errorMsg)) {
            $err = 'error:' . $this->errorMsg;
            $this->errorMsg = '';
        }

        $runTime = round(microtime(true) - $this->s, 6) * 1000;
        $dataLine = date('Y-m-d H:i:s');
        $simpleContent = "dataline: {$dataLine} runtime:{$runTime}  class:{$classExport} method:{$methodExport} args:{$argsExport}:\r\n  result:{$result}\r\n ";
        $content = $simpleContent . "client:{$client} {$err} \r\n";

        // 记录写操作
        if (empty($this->needReadonly)) {
            if (isset($args[0])) {
                if (is_array($args[0]) || !isset($this->notRedisRecord[@strtoupper($args[0])])) {
                    Log::write($simpleContent, LogLevel::REDISRECORD);
                }
            }
        }
        if (is_null($re) && !empty($err)) {
            Log::write($content, LogLevel::REDISERR);
        }
    }

    /**
     * hash中field分区
     * Enter description here ...
     *
     * @param unknown_type $key
     */
    private function getFieldIndex($field)
    {
        if (is_numeric($field)) {
            $fieldLen = strlen($field);
            if ($fieldLen > $this->indexFactor) {
                $index = substr($field, 0, $fieldLen - $this->indexFactor);
                $field = substr($field, -$this->indexFactor);
            } else {
                $index = 0;
            }
            return array($index, $field);
        } else {
            return null;
        }
    }

    /**
     * 获取hashkey 及field 组成key的slot
     * Enter description here ...
     *
     * @param unknown_type $key
     * @param unknown_type $field
     */
    private function getHashKey($keyName, $field)
    {
        // 是否分片
        if (in_array($keyName, $this->hashSharding)) {
            $item = $this->getFieldIndex($field);
            if (!is_null($item)) {
                $keyName = $keyName . ':' . $this->indexPre . $item[0];
                $field = $item[1];
            }
        }
        return array($keyName, $field);
    }
}