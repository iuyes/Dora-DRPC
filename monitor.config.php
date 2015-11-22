<?php
return array(
    //============
    //base config
    //============
    'export_path' => "./clinet.config.php",

    //this config for monitor
    'type' => "monitor",
    //request process timeout
    'timeout' => 3.0,
    //if sign the packet
    'datasign' => false,
    //for sign
    'salt' => "=&$*#@(*&%(@",

    //============
    //server port
    //============
    //for cmd
    'udp' => array(
        'ip' => "192.168.33.10",//must real ip not 0.0.0.0 or 127.0.0.1
        'port' => 9569,
    ),
    //for get all register nodes
    'redis' => array(
        array(
            "ip" => "127.0.0.1",
            "port" => "6379",
        ),
    ),

    //============
    //swoole server
    //============
    'swoole' => array(
        'open_length_check' => 1,
        'dispatch_mode' => 3,
        'package_length_type' => 'N',
        'package_length_offset' => 0,
        'package_body_offset' => 4,
        'package_max_length' => 1024 * 1024 * 2,
        'buffer_output_size' => 1024 * 1024 * 3,
        'pipe_buffer_size' => 1024 * 1024 * 32,
        'open_tcp_nodelay' => 1,
        'heartbeat_check_interval' => 5,
        'heartbeat_idle_time' => 10,

        'reactor_num' => 2,
        'worker_num' => 40,
        'task_worker_num' => 0,

        'max_request' => 0, //必须设置为0否则并发任务容易丢,don't change this number
        'task_max_request' => 4000,

        'backlog' => 2000,
        'log_file' => '/tmp/sw_monitor.log',
        'task_tmpdir' => '/tmp/swmonitor/',
        'daemonize' => 0,//product env is 1
    ),
);