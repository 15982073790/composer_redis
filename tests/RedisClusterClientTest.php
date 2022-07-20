<?php
/**
 * Created by PhpStorm.
 * User: 李勇刚
 * Date: 2020/7/28
 * Time: 9:34
 */

namespace Mrstock\Redis\Test;


use Mrstock\Helper\Config;
use Mrstock\Mjc\App;
use Mrstock\Redis\RedisClusterClient;
use PHPUnit\Framework\TestCase;

class RedisClusterClientTest extends TestCase
{
    //检查slots方法
    public function testSlots()
    {
        if (!Config::get('redis_config')) {
            $config['redis_hashsharding'] = array('stocksir:questions', 'stocksir:policys', 'stocksir:comments', 'stocksir:bbs', 'stocksir:member:message', 'stocksir:member:sms');
            $config['redis_config'] = array(
                'queue' => array(
                    'prefix' => 'QUEUE_',
                    'dynamicprefix' => ['site', 'appcode'],
                    'type' => 'redis',
                    'master' => array(array('host' => '192.168.10.231', 'port' => 6379, 'pconnect' => 0, 'db' => 3)),
                    'slave' => array(array('host' => '192.168.10.231', 'port' => 6379, 'pconnect' => 0, 'db' => 3))
                ),
                'cluster' => array(
                    'prefix' => 'stocksir_',
                    'type' => 'cluster',
                    'seeds_nodes' => array(
                        '192.168.10.243:7001',
                        '192.168.10.243:7001',
                        '192.168.10.243:7002',
                    ),
                    'slaves' => array(
                        '5460' => array(array('host' => '192.168.10.243', 'port' => 7000, 'pconnect' => 0, 'db' => 0)),
                        '10922' => array(array('host' => '192.168.10.243', 'port' => 7001, 'pconnect' => 0, 'db' => 0)),
                        '16383' => array(array('host' => '192.168.10.243', 'port' => 7002, 'pconnect' => 0, 'db' => 0))
                    ),
                )
            );
            Config::set($config);
            $config = $config['redis_config'];
        } else {
            $config = Config::get('redis_config');
        }
        $redisclusterclient = new RedisClusterClient($config['queue']['master']);

        $res = $redisclusterclient->slots(['192.168.10.231:6379']);
        //断言为空
        $this->assertEmpty($res);
        //断言为数组
        $this->assertIsArray($res);
    }

    //检查__call方法
    public function test__call()
    {
        if (!defined('VENDOR_DIR')) {
            define('VENDOR_DIR', __DIR__ . '/../vendor');
        }
        if (!Config::get('app')) {
            $config['app'] = new App();
            Config::set($config);
        }
        $config = Config::get('redis_config');
        $redisclusterclient = new RedisClusterClient($config['queue']['master']);

        $arguMents = [1, 3];
        $res = $redisclusterclient->__call('queue', $arguMents);

        //断言为空
        $this->assertEmpty($res);
        //断言值的范围
        $this->assertContains($res, [null, false, 0]);
    }

    //检查多keys 多值批量操作
    public function testGmulti()
    {
        $config = Config::get('redis_config');
        $redisclusterclient = new RedisClusterClient($config['queue']['master']);
        $data = array(
            'testmhset1' => ['call' => 'ghset', 'args' => [1, 1]],
            'testmhset2' => ['call' => 'ghmset', 'args' => [1, 2]]
        );
        $res = $redisclusterclient->gmulti($data);

        //断言为空
        $this->assertEmpty($res);
        //断言为数组
        $this->assertIsArray($res);
    }

}