<?php
/**
 * Created by PhpStorm.
 * User: 李勇刚
 * Date: 2020/7/28
 * Time: 9:11
 */

namespace Mrstock\Redis\Test;


use Mrstock\Helper\Config;
use Mrstock\Mjc\App;
use Mrstock\Redis\RedisCluster;
use PHPUnit\Framework\TestCase;

class RedisclusterTest extends TestCase
{
    //检查$constructSeedsNodes
    public function testConstructSeedsNodes()
    {
        $seeds_nodes = array(
            '192.168.10.243:7001',
            '192.168.10.243:7001',
            '192.168.10.243:7002',
        );
        $rediscluster = new RedisCluster($seeds_nodes);

        $res = $rediscluster->constructSeedsNodes;

        //不为空
        $this->assertNotEmpty($res);
        //断言为数组
        $this->assertIsArray($res);
        //断言数组个数
        $this->assertEquals(count($res), 3);
    }

    //检查__call
    public function test__call()
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
        }
        $seeds_nodes = array(
            '192.168.10.243:7001',
            '192.168.10.243:7001',
            '192.168.10.243:7002',
        );
        $rediscluster = new RedisCluster($seeds_nodes);

        $res = $rediscluster->__call('ghset', 21212);

        //断言为空
        $this->assertEmpty($res);
        //断言值的范围
        $this->assertContains($res, [null, false, 0]);
    }
}