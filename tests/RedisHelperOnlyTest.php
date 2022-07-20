<?php
/**
 * Created by PhpStorm.
 * User: 李勇刚
 * Date: 2020/7/28
 * Time: 13:17
 */

namespace Mrstock\Redis\Test;


use Mrstock\Helper\Config;
use Mrstock\Redis\RedisHelperOnly;
use PHPUnit\Framework\TestCase;

class RedisHelperOnlyTest extends TestCase
{
    //检查host方法
    public function testHost()
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
        }
        $redishelperonly = new RedisHelperOnly('queue', 3);

        $res = $redishelperonly->host('queue', 3);

        //断言不为空
        $this->assertNotEmpty($res);
        //断言为对象
        $this->assertIsObject($res);

    }

    //检查反射 redis/rediscluster 原生方法
    public function test__call()
    {
        $redishelperonly = new RedisHelperOnly('queue', 3);

        $res = $redishelperonly->__call('hehe', [1, 2]);

        //断言为空
        $this->assertEmpty($res);
        //断言值范围
        $this->assertContains($res, [false, null, 0]);
    }

    //检查多keys 多值批量操作 同一个key 只能一条命令
    public function testGpipe()
    {
        $redishelperonly = new RedisHelperOnly('queue', 3);

        $data = [
            'testmhset1' => ['call' => 'hset', 'args' => [1, 1]],
            'testmhset2' => ['call' => 'set', 'args' => [1]],
            'testmhset3' => ['call' => 'zadd', 'args' => [1, 1]],
            'testmhset4' => ['call' => 'hget', 'args' => [1]],
            'testmhset5' => ['call' => 'hgetall', 'args' => []]
        ];

        $res = $redishelperonly->gpipe($data);

        //断言不为空
        $this->assertNotEmpty($res);
        //断言为数组
        $this->assertIsArray($res);

        //断言testmhset1值
        $this->assertContains($res['testmhset1'], [1, 0, true, false]);
        //断言testmhset2值
        $this->assertContains($res['testmhset2'], [1, 0, true, false]);
        //断言testmhset3值
        $this->assertContains($res['testmhset3'], [1, 0, true, false]);
        //断言testmhset4值
        $this->assertContains($res['testmhset4'], [false, null, 0]);
        //断言testmhset5值
        $this->assertEquals($res['testmhset5'], []);

    }

    //检查多key事务 同一个key 只能一条命令
    public function testGtran()
    {
        $redishelperonly = new RedisHelperOnly('queue', 3);

        $data = [
            'test1' => ['call' => 'hset', 'args' => [1, 1]],
            'test2' => ['call' => 'set', 'args' => [1]],
            'test3' => ['call' => 'zadd', 'args' => [1, 1]],
        ];

        $res = $redishelperonly->gtran($data);

        //断言不为空
        $this->assertNotEmpty($res);
        //断言为数组
        $this->assertIsArray($res);

        //断言test1值
        $this->assertContains($res['test1'], [1, 0, true, false]);
        //断言test2值
        $this->assertContains($res['test2'], [1, 0, true, false]);
        //断言test3值
        $this->assertContains($res['test3'], [1, 0, true, false]);
    }
}