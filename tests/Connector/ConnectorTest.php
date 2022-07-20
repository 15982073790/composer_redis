<?php
/**
 * Created by PhpStorm.
 * User: 李勇刚
 * Date: 2020/7/27
 * Time: 13:36
 */

namespace Mrstock\Redis\Test;


use Mrstock\Helper\Config;
use Mrstock\Redis\Connector\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    public function testSetProperty()
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

        //配置不为空
        $this->assertNotEmpty($config);
        //配置为数组
        $this->assertIsArray($config);

        new Connector($config, 'cluster', 0, 'stocksir_', 'master', 1);

    }
}