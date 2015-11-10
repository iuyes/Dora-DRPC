<?php
include "../doradrpc/base/doraconst.class.php";
include "../doradrpc/base/packet.class.php";
include "../doradrpc/base/client.class.php";

$config = array(
    array("ip" => "192.168.33.10", "port" => 9567),
);

$obj = new \DoraDRPC\Base\Client($config);
$ret = $obj->getStat();
var_dump($ret);
