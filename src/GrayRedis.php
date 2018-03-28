<?php

namespace gray\level;

class GrayRedis {
    protected $handler;
    protected $options = [
        'persistent' => false,
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
    ];

    /**
     * 架构函数
     * @param array $options 配置redis初始化参数
     */
    public function __construct()
    {
        $options = $this->getConfig();

        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->handler = new \Redis;
        $func          = $this->options['persistent'] ? 'pconnect' : 'connect';
        $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

        if ('' != $this->options['password']) {
            $this->handler->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->handler->select($this->options['select']);
        }
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->handler,$method], $args);
    }

    //禁止clone
    private function __clone() {}

    public function __destruct() {
        $this->handler->close();
    }

    protected function getConfig()
    {
        // 加载配置文件
        $file_name_config = __DIR__ . '/../config/config_prod.php';
        if (defined(ENV)) {
            $file_name_config =  __DIR__ . '/../config/config_' . ENV . '.php';
        }

        if (!file_exists($file_name_config)) {
            throw new ServerException('灰度分配置文件不存在');
        }
        $config = include $file_name_config;

        return $config['redis']['gray_redis'];
    }
}