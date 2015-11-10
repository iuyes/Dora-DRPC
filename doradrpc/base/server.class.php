<?php
namespace DoraDRPC\Base;

abstract class Server
{

    protected $_server = null;

    protected $_ip = "0.0.0.0";
    protected $_port = 9567;

    protected $_config;

    //after new swoole server this function will be call
    //当创建完毕swoole服务后，在start服务之前会被调用，用来初始化一些服务使用，如监控进程，内存table库
    abstract public function callbackInitServer($server);

    //when a new client connected
    //当一个新连接连接后会被调用
    abstract public function onConnect($server, $fd);

    //when an udp packet arrive
    //就受到udp包触发
    abstract public function callbackPacket(\swoole_server $server, $data, $client_info);

    //when the packet arrive
    //收到packet后触发的回调
    abstract public function callbackRecive($server, $fd, $from_id, $data);

    //task process started and first init callback
    //task进程启动后初始化使用
    abstract public function callbackInitTask($server, $worker_id);

    //worker process startd first init callback
    //worker进程启动后初始化使用
    abstract public function callbackInitWorker($server, $worker_id);

    //do the task from client parameter return for result
    //处理具体业务任务，此进程为task内，处理完毕后使用return返回处理结果，有异常拦截处理
    abstract public function callbackDoWork($param);

    //when the worker got error will call,then killed processs (manager will start new one )
    //当worker报错会调用此函数进行现场信息收集，然后会释放掉此进程(管理进程会自动重启一个新的)
    abstract public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code);

    //when the task return the result（callbackdowork） will call this function "Format" and send back
    //当dowork完成后返回结果，都会调用此函数进行整理，并且由此函数内调用send反馈结果。
    abstract public function callbackProcessResult($serv, $task_id, $data);

    abstract public function onClose(\swoole_server $server, $fd, $from_id);


    final public function __construct($config)
    {

        $this->_config = $config;
        //record ip:port
        $this->_ip = $config["tcp"]["ip"];
        $this->_port = $config["tcp"]["port"];

        //create server object
        $this->_server = new \swoole_server($config["tcp"]["ip"], $config["tcp"]["port"]);
        //set config
        $this->_server->set($config["swoole"]);

        //register the event
        $this->_server->on('ManagerStart', array($this, 'onManagerStart'));
        $this->_server->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->_server->on('Connect', array($this, 'onConnect'));
        $this->_server->on('Receive', array($this, 'onReceive'));
        $this->_server->on('Packet', array($this, 'onPacket'));

        $this->_server->on('WorkerError', array($this, 'onWorkerError'));
        $this->_server->on('Task', array($this, 'onTask'));
        $this->_server->on('Finish', array($this, 'onFinish'));
        $this->_server->on('Close', array($this, 'onClose'));

        //invoke the start callback
        $this->callbackInitServer($this->_server);

        echo "Start Init Server tcp://" . $config["tcp"]["ip"] . ":" . $config["tcp"]["port"] . PHP_EOL;

        //start server
        $ret = $this->_server->start();
        if ($ret) {
            echo "Server Start Success...";
        } else {
            echo "Server Start Fail...Exit";
            exit;
        }

    }

    public function onManagerStart(\swoole_server $server)
    {
        //on manager start
        swoole_set_process_name("DoraDRPC_ProcessManager");
    }

    final public function onWorkerStart($server, $worker_id)
    {
        //is task
        $isTask = $server->taskworker;

        if (!$isTask) {
            //worker
            swoole_set_process_name("DoraDRPC_WORKER|{$worker_id}");
            $this->callbackInitWorker($server, $worker_id);
        } else {
            //task
            swoole_set_process_name("DoraDRPC_TASK|{$worker_id}|Started");
            $this->callbackInitTask($server, $worker_id);
        }
    }

    //on Recive Callback
    public function onReceive(\swoole_server $server, $fd, $from_id, $data)
    {
        //packet decode
        $requestObj = \DoraDRPC\Base\Packet::packDecode($data);

        #decode error
        if ($requestObj["code"] != 0) {
            $requestString = \DoraDRPC\Base\Packet::packEncode($requestObj);
            $server->send($fd, $requestString);
            return true;
        }

        //call back to process
        return $this->callbackRecive($server, $fd, $from_id, $requestObj["data"]);
    }

    //on packet arrive
    public function onPacket(\swoole_server $server, $data, $client_info)
    {
        return $this->callbackPacket($server, $data, $client_info);
    }

    final public function onTask($serv, $task_id, $from_id, $data)
    {
        swoole_set_process_name("DoraDRPC_TASK|$task_id|{$data["api"]["name"]}");

        try {
            $data["result"] = $this->callbackDoWork($data);

            //fixed the result more than 8k timeout bug
            $data = serialize($data);
            if (strlen($data) > 8000) {
                $temp_file = tempnam(sys_get_temp_dir(), 'swmore8k');
                file_put_contents($temp_file, $data);
                return '$$$$$$$$' . $temp_file;
            } else {
                return $data;
            }

        } catch (\Exception $e) {
            $data["result"] = \DoraDRPC\Base\Packet::packFormat($e->getMessage(), $e->getCode());
            return $data;
        }

    }


    public function onFinish($serv, $task_id, $data)
    {
        //fixed the result more than 8k timeout bug
        if (strpos($data, '$$$$$$$$') === 0) {
            $tmp_path = substr($data, 8);
            $data = file_get_contents($tmp_path);
            unlink($tmp_path);
        }
        $data = unserialize($data);

        $this->callbackProcessResult($serv, $task_id, $data);

        return true;
    }


    final public function __destruct()
    {
        $this->_server->shutdown();
    }

}