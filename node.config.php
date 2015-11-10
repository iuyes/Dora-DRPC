<?php
return array(
    'tcp' => array(
        'ip' => "0.0.0.0",
        'port' => 9567,
    ),
    'udp' => array(
        'ip' => "0.0.0.0",
        'port' => 9568,
    ),
    'group' => array(
        "list" => array(
            'group1',
            'group2',
        ),
    ),
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

        'reactor_num' => 32,
        'worker_num' => 40,
        'task_worker_num' => 20,

        'max_request' => 0, //必须设置为0否则并发任务容易丢,don't change this number
        'task_max_request' => 4000,

        'backlog' => 2000,
        'log_file' => '/tmp/sw_server.log',
        'task_tmpdir' => '/tmp/swtasktmp/',

        //'daemonize' => 1,
    ),
);