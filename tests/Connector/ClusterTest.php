<?php
/**
 * Created by PhpStorm.
 * User: 李勇刚
 * Date: 2020/7/27
 * Time: 10:33
 */

namespace Mrstock\Redis\Test;

use Mrstock\Helper\Config;
use PHPUnit\Framework\TestCase;

class ClusterTest extends TestCase
{
    //检查get方法
    public function testGet()
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
        $cluster = new \Mrstock\Redis\Connector\Cluster($config, 'cluster', 3, 'stocksir_', 'master', 1);

        $res = $cluster->get();

        //断言不为空
        $this->assertNotEmpty($res);
        //断言为对象
        $this->assertIsObject($res);
        //断言seeds_nodes
        $this->assertIsArray($res->constructSeedsNodes);
    }
}