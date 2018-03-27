<?php

namespace gray\level;

class GrayDb
{
    public static function get($index)
    {
        static $mysqli_gray;
        if (empty($mysqli_gray[$index])) {
            $mysqli_gray[$index] = new Mysql(self::getDbConfig($index));
        }
        return $mysqli_gray[$index];
    }

    public static function getDbConfig($index)
    {
        // 加载配置文件
        $file_name_config = __DIR__ . '/../config/config_prod.php';
        if (define(ENV)) {
            $file_name_config =  __DIR__ . '/../config/config_' . ENV . '.php';
        }
        
        if (!file_exists($file_name_config)) {
            throw new ServerException('灰度分配置文件不存在');
        }
        $config = include $file_name_config;

        return $config['mysql'][$index];
    }
}
