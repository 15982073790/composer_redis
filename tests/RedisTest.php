<?php
/**
 * Created by PhpStorm.
 * User: 李勇刚
 * Date: 2020/7/27
 * Time: 14:18
 */

namespace Mrstock\Redis\Test;

use Mrstock\Helper\Config;
use Mrstock\Mjc\App;
use Mrstock\Redis\Redis;
use PHPUnit\Framework\TestCase;

class RedisTest extends TestCase
{
    //检查setClientId方法
    public function testSetClientId()
    {
        if (!defined('VENDOR_DIR')) {
            define('VENDOR_DIR', __DIR__ . '/../vendor');
        }

        if (!Config::get('app')) {
            $config['app'] = new App();
            Config::set($config);
        }

        if (!Config::get('redis_config')) {
            $config['redis_hashsharding'] = array('stocksir:questions', 'stocksir:policys', 'stocksir:comments', 'stocksir:bbs', 'stocksir:member:message', 'stocksir:member:sms');
            Config::set($config);
            $config = $config['redis_hashsharding'];
        }

        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $redis->setClientId($ip, $port, $func, 3);

        $s = $redis->s;

        //断言不为空
        $this->assertNotEmpty($s);
        //断言为字符串
        $this->assertIsFloat($s);

    }

    //检查是否需要发送readonly
    public function testNeedReadonly()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $needReadonly = $redis->needReadonly;

        //断言数字
        $this->assertIsInt($needReadonly);
        //断言为0
        $this->assertContains($needReadonly, [1, 0]);
    }

    //检查setSeedsNodes方法
    public function testSetSeedsNodes()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);
        $seeds_nodes = array(
            '192.168.10.243:7001',
            '192.168.10.243:7001',
            '192.168.10.243:7002',
        );
        $res = $redis->setSeedsNodes($seeds_nodes);

        //检查不为空
        $this->assertNotEmpty($res);
        //检查为bool
        $this->assertIsBool($res);
        //检查值
        $this->assertEquals($res, 1);
    }

    //检查init方法
    public function testInit()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->init();

        //检查为空
        $this->assertEmpty($res);
        //检查值
        $this->assertContains($res, [false, null, 0]);
    }

    //检查haveReadonly方法
    public function testHaveReadonly()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->haveReadonly();

        //检查为空
        $this->assertEmpty($res);
        //检查值
        $this->assertContains($res, [false, null, 0]);
    }

    //检查setChildClassName方法
    public function testSetChildClassName()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->setChildClassName('Redis');

        //检查不为空
        $this->assertNotEmpty($res);
        //检查为bool
        $this->assertIsBool($res);
        //检查值
        $this->assertEquals($res, 1);
    }

    //检查单参数使用
    public function test__call()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->__call('ghset', 21212);

        //检查为空
        $this->assertEmpty($res);
        //检查值
        $this->assertContains($res, [false, null, 0]);
    }

    //检查ghset方法
    public function testGhset()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->ghset('redis2', 'redis2', 'redis998');
        //检查值
        $this->assertContains($res, [1, 0]);
    }

    //检查ghget方法
    public function testGhget()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->ghget('redis', 'redis');
        if ($res) {
            //检查不为空
            $this->assertNotEmpty($res);
            //检查为bool
            $this->assertIsString($res);
            //检查值
            $this->assertEquals($res, 'redis');
        } else {
            //检查不为空
            $this->assertEmpty($res);
        }
    }

    //检查ghdel方法
    public function testGhdel()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->ghdel('redis', 'redis');

        //检查值
        $this->assertContains($res, [1, 0]);
    }

    //检查ghmset方法
    public function testGhmset()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->ghmset('queue_key', ['id' => 11, 'name' => 'hehe']);

        //检查不为空
        $this->assertNotEmpty($res);
        //检查为bool
        $this->assertIsBool($res);
        //检查值
        $this->assertEquals($res, 1);
    }

    //检查ghmget方法
    public function testGhmget()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $res = $redis->ghmget('queue_key', ['name']);

        //检查不为空
        $this->assertNotEmpty($res);
        //检查为bool
        $this->assertIsArray($res);
        //检查值
        $this->assertEquals($res['name'], 'hehe');
    }

    //检查多keys 多值批量操作
    public function testGmulti()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $data = array(
            'testmhset1' => ['call' => 'ghset', 'args' => [1, 1]],
            'testmhset2' => ['call' => 'ghmset', 'args' => [1, 2]]
        );

        $res = $redis->gmulti($data, true);

        //断言不为空
        $this->assertNotEmpty($res);
        //断言为数组
        $this->assertIsArray($res);
    }

    //检查checkResult方法
    public function testCheckResult()
    {
        $ip = '192.168.10.231';
        $port = 6379;
        $func = 'connect';

        $redis = new Redis($ip, $port, $func, 3);

        $data['testmhset1'] = [1, 1];
        $data['testmhset2'] = [1, 2];
        //断言不为空
        $this->assertNotEmpty($data);
        //断言为数组
        $this->assertIsArray($data);

        $redis->checkResult($data, __METHOD__, func_get_args());

    }
}