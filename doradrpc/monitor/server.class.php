<?php
namespace DoraDRPC\Monitor;

class Server
{
    protected $_server = null;

    protected $_ip = "0.0.0.0";
    protected $_port = 9567;

    protected $_config;

    //server report
    final public function generalConfig(\swoole_process $process)
    {
        static $_redisObj = array();

        while (true) {
            //for result list
            $server_list_result = array();

            //get redis config
            $redisconfig = $this->_config["redis"];

            //connect all redis
            foreach ($redisconfig as $redisitem) {
                //validate redis ip and port
                if (trim($redisitem["ip"]) && $redisitem["port"] > 0) {
                    $key = $redisitem["ip"] . "_" . $redisitem["port"];
                    try {
                        //connecte redis
                        if (!isset($_redisObj[$key])) {
                            //if not connect
                            $_redisObj[$key] = new \Redis();
                            $_redisObj[$key]->connect($redisitem["ip"], $redisitem["port"]);
                        }

                        //get register node server
                        $server_list = $_redisObj[$key]->smembers("doradrpc.serverlist");
                        if ($server_list) {
                            foreach ($server_list as $sitem) {
                                $info = json_decode($sitem, true);
                                //decode success
                                if ($info) {
                                    //get lsta report time
                                    $lasttimekey = $info["node"]["ip"] . "_" . $info["node"]["port"] . "_time";
                                    $lastupdatetime = $_redisObj[$key]->get($lasttimekey);

                                    //timeout ignore
                                    if (time() - $lastupdatetime > 20) {
                                        continue;
                                    }

                                    //foreach group and record this info
                                    foreach ($info["group"]["list"] as $groupname) {

                                        $server_list_result[$groupname][$key]["info"] = $info;
                                        $server_list_result[$groupname][$key]["lasttime"] = $lastupdatetime;
                                    }

                                }//decode info if
                            }// foreach
                        }//if got server list from redis

                    } catch (\Exception $ex) {
                        //var_dump($ex);
                        $_redisObj[$key] = null;
                        echo "get redis server error" . PHP_EOL;
                    }
                }
            }

            if (count($server_list_result) > 0) {
                $configString = var_export($server_list_result, true);
                $ret = file_put_contents($this->_config["export_path"], "<?php" . PHP_EOL . "return " . $configString . ";");
                if (!$ret) {
                    echo "Error save the config to file..." . PHP_EOL;
                } else {
                    echo "General config file to:" . $this->_config["export_path"] . PHP_EOL;
                }
            } else {
                echo "Error there is no Config get..." . PHP_EOL;
            }

            //sleep 10 sec
            sleep(10);
        }
    }

    public function __construct($config)
    {
        $this->_config = $config;

        if ($config["type"] != "monitor") {
            echo "Error this config is not for node...Exit";
            exit(-1);
        }

        //record ip:port
        $this->_ip = $config["udp"]["ip"];
        $this->_port = $config["udp"]["port"];

        //create server object
        $this->_server = new \swoole_server($config["udp"]["ip"], $config["udp"]["port"], \SWOOLE_PROCESS, \SWOOLE_SOCK_UDP);
        //set config
        $this->_server->set($config["swoole"]);

        //register the event
        $this->_server->on('Packet', array($this, 'onPacket'));

        echo "Start Init Server udp://" . $config["udp"]["ip"] . ":" . $config["udp"]["port"] . PHP_EOL;

        //use this for generalConfig by cycle
        $process = new \swoole_process(array($this, "generalConfig"));
        $this->_server->addProcess($process);

        //start server
        $ret = $this->_server->start();
        if ($ret) {
            echo "Server Start Success...";
        } else {
            echo "Server Start Fail...Exit";
            exit;
        }
    }

    //when an udp packet arrive
    //就受到udp包触发
    public function onPacket(\swoole_server $server, $data, $client_info)
    {
        $data = \DoraDRPC\Base\Packet::packDecode($data);
        //$server->sendto($client_info['address'], $client_info['port'], \DoraDRPC\Base\Packet::packEncode(array()));

        //var_dump($server, $data);
    }

}