<?php
/**
 * Created by PhpStorm.
 * User: 李勇刚
 * Date: 2020/7/27
 * Time: 14:04
 */

namespace Mrstock\Redis\Test;


use Mrstock\Helper\Config;
use Mrstock\Redis\Connector\Single;
use PHPUnit\Framework\TestCase;

class SingleTest extends TestCase
{
    //检查设置非集群redis
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
            );
            Config::set($config);
            $config = $config['redis_config'];
        } else {
            $config = Config::get('redis_config');
        }

        $single = new Single($config, 'queue', 3, 'QUEUE_', 'master', 1);

        $res = $single->get();

        //断言不为空
        $this->assertNotEmpty($res);
        //断言为对象
        $this->assertIsObject($res);
    }
}