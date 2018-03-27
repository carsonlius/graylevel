<?php

namespace gray\level;

class GrayMysql extends \mysqli
{
    public function __construct($config)
    {
        parent::__construct($config['host'], $config['user'], $config['password'], $config['dbname'], $config['port']);
        if ($this->connect_error) {
            throw new ServerException('databases[' . $config['dbname'] . '] ' . $this->connect_errno);
        }
        $this->set_charset($config['charset']);
    }

    public function dhbSelect($sql, $key = '')
    {
        $result = $this->query($sql);
        if ($result === false) {
            throw new ServerException('databases error:' . $this->error);
        }
        $lists = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            if (!empty($key)) {
                $lists[$row[$key]] = $row;
            } else {
                $lists[] = $row;
            }
        }
        $result->free();
        return $lists;
    }

    public function dhbGetOne($sql)
    {
        $lists = $this->dhbSelect($sql);
        return isset($lists[0]) ? $lists[0] : null;
    }

    public function dhbInsert($lists, $table)
    {
        $this->dhbBatchInsert($lists, $table);

        return $this->insert_id;
    }

    public function dhbBatchInsert($lists, $table)
    {
        $info = [];
        if (!isset($lists[0]) || !is_array($lists[0])) {
            $info[] = $lists;
        } else {
            $info = $lists;
        }

        $key = array_keys($info[0]);
        $key = '(`' . implode('`,`', $key) . '`)';
        $values = [];
        foreach ($info as $var) {
            $values[] = "('" . implode("','", array_map('addslashes', $var)) . "')";
        }
        $value = implode(',', $values);

        $sql = "INSERT INTO `" . $table . "` " . $key . " VALUES " . $value;
        $this->query($sql);
        return $this->insert_id;
    }
}
