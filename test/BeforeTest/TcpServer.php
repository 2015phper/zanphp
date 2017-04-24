<?php
/**
 * Created by PhpStorm.
 * User: marsnowxiao
 * Date: 2017/4/20
 * Time: 下午5:42
 */

$server = new swoole_server('0.0.0.0', 2380);

$server->set([
    /*'open_length_check' => 1,
    'package_length_type' => 'N',
    'package_length_offset' => 0,
    'package_body_offset' => 0,
    'open_nova_protocol' => 1*/
]);

$server->on("receive", function ($server, $fd, $from_id, $data) {
    echo "received $data\n";
    $server->send($fd, $data);
});

$server->start();