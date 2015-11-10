<?php
namespace DoraDRPC\Node;

use DoraDRPC\Base\DoraConst;

class Server extends \DoraDRPC\Base\Server
{

    private $_taskinfo = array();

    //////////////////////////////server monitor start/////////////////////////////
    //server report
    final public function monitorReport(\swoole_process $process)
    {
        while (true) {
            //server static
            $serverStatic = $this->get_used_status();

            //server accept msg group
            $serverGroup = $this->_config["group"]["list"];

            //暂缺 多磁盘空间 系统tcp连接数 swap记录 网卡流量
            //var_dump($serverStatic);
            //sleep 10 sec and report again
            sleep(10);
        }
    }

    //get used status
    function get_used_status()
    {
        //cp from http://blog.sina.com.cn/s/blog_c26f70970101k5sz.html
        $fp = popen('top -b -n 2 | grep -E "^(Cpu|Mem|Tasks)"', "r");//获取某一时刻系统cpu和内存使用情况
        $rs = "";
        while (!feof($fp)) {
            $rs .= fread($fp, 1024);
        }
        pclose($fp);

        $sys_info = explode("\n", $rs);
        $tast_info = explode(",", $sys_info[3]);//进程 数组
        $cpu_info = explode(",", $sys_info[4]);  //CPU占有量  数组
        $mem_info = explode(",", $sys_info[5]); //内存占有量 数组
        //正在运行的进程数
        $tast_running = trim(trim($tast_info[1], 'running'));

        //CPU占有量
        $cpu_usage = trim(trim($cpu_info[0], 'Cpu(s): '), '%us');  //百分比

        //内存占有量
        $mem_total = trim(trim($mem_info[0], 'Mem: '), 'k total');
        $mem_used = trim($mem_info[1], 'k used');
        $mem_usage = round(100 * intval($mem_used) / intval($mem_total), 2);  //百分比

        $fp = popen('df -lh | grep -E "^(/)"', "r");
        $rs = fread($fp, 1024);
        pclose($fp);
        $rs = preg_replace("/\s{2,}/", ' ', $rs);  //把多个空格换成 “_”
        $hd = explode(" ", $rs);
        $hd_avail = trim($hd[3], 'G'); //磁盘可用空间大小 单位G
        $hd_usage = trim($hd[4], '%'); //挂载点 百分比

        $result = array(
            "process_running_count" => $tast_running,
            "cpu_usage_percent" => $cpu_usage,
            "mem_total" => $mem_total,
            "mem_used" => $mem_used,
            "mem_usage_percent" => $mem_usage,
            "hd_avaliable" => $hd_avail,
            "hd_used_percent" => $hd_usage,
            "host" => $this->_ip,
            "port" => $this->_port,
            "server" => $this->_server->stats(),
            "load" => sys_getloadavg(),
        );
        return $result;
    }

    //after new swoole server this function will be call
    //当创建完毕swoole服务后，在start服务之前会被调用，用来初始化一些服务使用，如监控进程，内存table库
    public function callbackInitServer($server)
    {
        //get server config
        $config = $this->_config;

        //open controller udp port
        $server->addlistener($config["udp"]["ip"], $config["udp"]["port"], SWOOLE_SOCK_UDP);

        //use this report the state
        $process = new \swoole_process(array($this, "monitorReport"));
        $server->addProcess($process);
    }

    //////////////////////////////server monitor stop/////////////////////////////

    //when a new client connected
    //当一个新连接连接后会被调用
    public function onConnect($server, $fd)
    {
        $this->_taskinfo[$fd] = array();
    }

    //when an udp packet arrive
    //就受到udp包触发
    public function callbackPacket(\swoole_server $server, $data, $client_info)
    {
        //var_dump($server, $data);
    }


    //when the packet arrive
    //收到packet后触发的回调
    public function callbackRecive($server, $fd, $from_id, $data)
    {
        $this->_taskinfo[$fd] = $data;

        $task = array(
            "type" => $this->_taskinfo[$fd]["type"],
            "guid" => $this->_taskinfo[$fd]["guid"],
            "fd" => $fd,
        );

        //by the type to decide
        switch ($this->_taskinfo[$fd]["type"]) {
            //single sync call
            case \DoraDRPC\Base\DoraConst::SW_SYNC_SINGLE:
                $task["api"] = $this->_taskinfo[$fd]["api"]["one"];
                $taskid = $server->task($task);

                $this->_taskinfo[$fd]["task"][$taskid] = "one";

                return true;
                break;
            case \DoraDRPC\Base\DoraConst::SW_ASYNC_SINGLE:
                $task["api"] = $this->_taskinfo[$fd]["api"]["one"];
                $server->task($task);

                $pack = \DoraDRPC\Base\Packet::packFormat("transfer success.已经成功投递", 100001);
                $pack["guid"] = $task["guid"];
                $pack = \DoraDRPC\Base\Packet::packEncode($pack);
                $server->send($fd, $pack);

                unset($this->_taskinfo[$fd]);

                return true;
                break;

            case \DoraDRPC\Base\DoraConst::SW_SYNC_MULTI:
                foreach ($data["api"] as $k => $v) {
                    $task["api"] = $this->_taskinfo[$fd]["api"][$k];
                    $taskid = $server->task($task);
                    $this->_taskinfo[$fd]["task"][$taskid] = $k;
                }

                return true;
                break;
            case \DoraDRPC\Base\DoraConst::SW_ASYNC_MULTI:
                foreach ($data["api"] as $k => $v) {
                    $task["api"] = $this->_taskinfo[$fd]["api"][$k];
                    $server->task($task);
                }

                $pack = \DoraDRPC\Base\Packet::packFormat("transfer success.已经成功投递", 100001);
                $pack["guid"] = $task["guid"];
                $pack = \DoraDRPC\Base\Packet::packEncode($pack);

                $server->send($fd, $pack);
                unset($this->_taskinfo[$fd]);

                return true;
                break;
            case DoraConst::SW_CONTROL_CMD:
                if ($this->_taskinfo[$fd]["api"]["cmd"]["name"] == "getStat") {
                    $pack = \DoraDRPC\Base\Packet::packFormat("OK", 0, array("server" => $server->stats()));
                    $pack["guid"] = $task["guid"];
                    $pack = \DoraDRPC\Base\Packet::packEncode($pack);
                    $server->send($fd, $pack);
                    unset($this->_taskinfo[$fd]);
                    return true;
                }

                //no one process
                $pack = \DoraDRPC\Base\Packet::packFormat("unknow cmd", 100011);
                $pack = \DoraDRPC\Base\Packet::packEncode($pack);

                $server->send($fd, $pack);
                unset($this->_taskinfo[$fd]);
                break;
            default:
                $pack = \DoraDRPC\Base\Packet::packFormat("unknow task type.未知类型任务", 100002);
                $pack = \DoraDRPC\Base\Packet::packEncode($pack);

                $server->send($fd, $pack);
                unset($this->_taskinfo[$fd]);

                return true;
        }

        return true;
    }

    //task process started and first init callback
    //task进程启动后初始化使用
    public function callbackInitTask($server, $worker_id)
    {

    }

    //worker process startd first init callback
    //worker进程启动后初始化使用
    public function callbackInitWorker($server, $worker_id)
    {

    }

    //do the task from client parameter return for result
    //处理具体业务任务，此进程为task内，处理完毕后使用return返回处理结果，有异常拦截处理
    public function callbackDoWork($param)
    {
        return array("oak" => "cda");
    }

    //when the worker got error will call,then killed processs (manager will start new one )
    //当worker报错会调用此函数进行现场信息收集，然后会释放掉此进程(管理进程会自动重启一个新的)
    public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code)
    {

    }

    //when the task return the result（callbackdowork） will call this function "Format" and send back
    //当dowork完成后返回结果，都会调用此函数进行整理，并且由此函数内调用send反馈结果。
    public function callbackProcessResult($serv, $task_id, $data)
    {

        $fd = $data["fd"];

        if (!isset($this->_taskinfo[$fd]) || !$data["result"]) {
            unset($this->_taskinfo[$fd]);

            return true;
        }
        $key = $this->_taskinfo[$fd]["task"][$task_id];
        $this->_taskinfo[$fd]["result"][$key] = $data["result"];

        unset($this->_taskinfo[$fd]["task"][$task_id]);

        switch ($data["type"]) {

            case DoraConst::SW_SYNC_SINGLE:
                $Packet = \DoraDRPC\Base\Packet::packFormat("OK", 0, $data["result"]);
                $Packet["guid"] = $this->_taskinfo[$fd]["guid"];
                $Packet = \DoraDRPC\Base\Packet::packEncode($Packet);

                $serv->send($fd, $Packet);
                unset($this->_taskinfo[$fd]);

                return true;
                break;

            case DoraConst::SW_SYNC_MULTI:
                if (count($this->_taskinfo[$fd]["task"]) == 0) {
                    $Packet = \DoraDRPC\Base\Packet::packFormat("OK", 0, $this->_taskinfo[$fd]["result"]);
                    $Packet["guid"] = $this->_taskinfo[$fd]["guid"];
                    $Packet = \DoraDRPC\Base\Packet::packEncode($Packet);

                    $serv->send($fd, $Packet);
                    unset($this->_taskinfo[$fd]);

                    return true;
                } else {
                    return true;
                }
                break;

            default:
                unset($this->_taskinfo[$fd]);

                return true;
                break;
        }
    }

    public function onClose(\swoole_server $server, $fd, $from_id)
    {
        unset($this->_taskinfo[$fd]);
    }
}