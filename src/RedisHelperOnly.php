<?php

namespace Mrstock\Redis;

use Mrstock\Helper\Config;
use Mrstock\Helper\KeyValue;

/**
 * Redishelper 操作类
 */
class RedisHelperOnly
{

    /**
     * key 前缀(配置+动态)
     *
     * @var unknown
     */
    protected $prefix;

    protected $host;

    protected $db;

    protected $masterOrSlave;

    protected $type;

    protected $config;

    protected $readonly;

    protected static $handle;

    protected $slaveCommand = ['HGET', 'HGETALL', 'HKEYS', 'HLEN', 'HMGET', 'HSTRLEN', 'HVALS', 'HSCAN', 'SORT', 'TTL', 'TYPE', 'SCARD', 'SISMEMBER', 'SMEMBERS', 'SRANDMEMBER', 'SSCAN', 'ZCARD', 'ZCOUNT', 'ZLEXCOUNT', 'ZRANGE', 'ZRANGEBYLEX', 'ZREVRANGEBYLEX', 'ZRANGEBYSCORE', 'ZRANK', 'ZREVRANGE', 'ZREVRANGEBYSCORE', 'ZREVRANK', 'ZSCORE', 'ZSCAN', 'BITCOUNT', 'GET', 'GETBIT', 'MGET', 'STRLEN'
    ];

    public function __construct($host = 'appcluster', $db = '0')
    {
        // 获取redis集群配置文件
        $this->config = Config::Get('redis_config');

        $this->init($host, $db);
    }

    public function host($host = 'appcluster', $db = '0')
    {
        $this->init($host, $db);

        return $this;
    }

    protected function init($host, $db)
    {
        if (empty($host) || $host == 'master' || $host == 'slave') {
            $host = "appcluster";
        }
        $this->host = $host;
        $this->db = intval($db);

        $this->type = $this->config[$this->host]['type'];
        if ($this->type == 'redis') {
            $this->type = 'single';
        }
    }

    /**
     * 反射 redis/rediscluster 原生方法
     *
     * @param string $command
     *            (不支持 多key命令 不支持key不在第一个参数的命令)
     * @param array $arguMents
     * @return mixed
     */
    public function __call($command, $arguMents)
    {
        $this->parerMasterSlave($command);

        $this->setHandle();

        $arguMents[0] = $this->setKey($arguMents[0]);

        $re = call_user_func_array(array($this->handle, $command
        ), $arguMents);
        return $re;
    }

    protected function parerMasterSlave($command)
    {
        $this->masterOrSlave = 'master';
        $this->readonly = 0;
        if (isset($this->slaveCommand[$command])) {
            $this->readonly = 1;
            $this->masterOrSlave = 'slave';
        }
    }

    protected function setHandle()
    {


        $this->setPrefix();

        $class = '\Mrstock\\Redis\\Connector\\' . ucwords($this->type);
        $redisConnector = new $class($this->config, $this->host, $this->db, $this->prefix, $this->masterOrSlave, $this->readonly);

        $this->handle = $redisConnector->get();


    }

    /**
     *
     * 多keys 多值批量操作 同一个key 只能一条命令
     *
     * @param unknown_type $data
     *            [
     *            'testmhset1'=>['call'=>'hset','args'=>[1,1]],
     *            'testmhset2'=>['call'=>'set','args'=>[1]],
     *            'testmhset3'=>['call'=>'zadd','args'=>[1,1]],
     *            'testmhset4'=>['call'=>'hget','args'=>[1]],
     *            'testmhset5'=>['call'=>'hgetall','args'=>[]]
     *            ]
     * @param unknown_type $host
     * @param unknown_type $master
     */
    public function gpipe($data)
    {
        return $this->adaptedGroupKey($data, true);
    }

    /**
     * 多key事务 同一个key 只能一条命令
     * Enter description here ...
     *
     * @param unknown_type $data
     * @param unknown_type $host
     * @param unknown_type $master
     */
    public function gtran($data)
    {
        return $this->adaptedGroupKey($data, false);
    }

    protected function adaptedGroupKey($data, $pipe = true)
    {
        if (!is_array($data) || count($data) == 0) {
            return array();
        }

        $this->initPipe();

        $tmp = array();
        $sourceData = array();
        foreach ($data as $k => $v) {
            $sourceData[$k] = 1;
            $nk = $this->setKey($k);
            $tmp[$nk] = $v;
        }

        if ($pipe) {
            $result = $this->handle->gmulti($tmp, true);
        } else {
            $result = $this->handle->gmulti($tmp);
        }

        foreach ($sourceData as $k => $v) {
            if (!isset($result[$k])) {
                $tmpKey = $this->adaptedKey($k);
                $result[$k] = $result[$tmpKey];
            }
        }
        return $result;
    }

    /**
     * 集群转换host
     *
     * @param string $host
     * @param int $db
     */
    protected function initPipe()
    {
        $this->masterOrSlave = 'master';
        $this->readonly = 0;

        //$pipeHost = $this->host . '_pipe';
        //$this->config[$pipeHost] = $this->config[$this->host];
        if ($this->type == 'cluster') {
            $this->type = 'clusterclient';
        }
        //$this->host = $pipeHost;

        $this->setHandle();
    }

    /**
     * 组装key
     *
     * @param unknown $key
     * @return mixed
     */
    protected function setKey($key)
    {
        $tmpKey = $this->prefix . $key;
        $tmpKey = $this->adaptedKey($tmpKey);
        return $tmpKey;
    }

    /**
     * 统一 Key 分隔符
     *
     * @param unknown $key
     * @return mixed
     */
    protected function adaptedKey($key)
    {
        $tmpKey = str_replace('_', ':', $key);
        $tmpKey = str_replace('-', ':', $tmpKey);
        return $tmpKey;
    }

    /**
     * 设置 key 前缀
     */
    protected function setPrefix()
    {
        $this->prefix = $this->config[$this->host]['prefix'];

        $dynamicPrefix = KeyValue::dynamicPrefix($this->config[$this->host]);

        $this->prefix = $dynamicPrefix . $this->prefix;
        $this->prefix = $this->adaptedKey($this->prefix);
    }
}