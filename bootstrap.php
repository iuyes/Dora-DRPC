<?php
swoole_set_process_name("DoraDRPC_Bootstrap");

function autoloadClass($classname)
{
    //explode \
    $classname = explode("\\", $classname);

    //to low case
    foreach ($classname as &$subpath) {
        $subpath = strtolower($subpath);
    }

    //get class file
    $classpath = $classname;
    $classname = array_pop($classpath);
    $classpath = implode("/", $classpath);
    $classpath = $classpath . "/" . $classname . ".class.php";

    //if have the file
    if (file_exists($classpath)) {
        include_once $classpath;
        return true;
    } else {
        return false;
    }
}

function showhelp()
{
    echo "DoraDRPC Help v0.4" . PHP_EOL;
    echo "  node        Backend API Service Server" . PHP_EOL;
    echo "  monitor     group Monitor" . PHP_EOL;
    echo "  logserver   Log collect server and store" . PHP_EOL;
    echo "  clientagent Local config and client" . PHP_EOL;
    echo "" . PHP_EOL;
    return;
}

spl_autoload_register("autoloadClass");

if ($argc > 1) {
    switch (strtolower(trim($argv[1]))) {
        case "node":
            $config = include(trim($argv[2]));
            if ($config) {
                $server = new DoraDRPC\Node\Server($config);
            } else {
                showhelp();
            }

            break;
        case "monitor":
            $config = include(trim($argv[2]));
            if ($config) {
                $server = new DoraDRPC\Monitor\Server($config);
            } else {
                showhelp();
            }

            break;
        case "logserver":
            break;
        case "clientagent":
            break;
        default:
            showhelp();
    }
} else {
    showhelp();
    return;
}