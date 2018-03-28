<?php

namespace gray\level;

class GrayLevelScore
{
    protected static $col_base = 'gray_level_score_';
    protected static $allow_batch_num = 50;
    protected static $log_table = 'gray_score_invoked_log';
    protected static $redis_key_gray_score_prefix = 'gray_score_';

    public static function getScore($search_tels, $search_rely = [], $response_type = 'array')
    {
        //check redis cache
        $redis = new GrayRedis();
        $search_tels = is_array($search_tels) ? $search_tels : (array)$search_tels;
        $redis_common_gray_key = md5(json_encode($search_tels));
        $redis_gray_cache_key = self::$redis_key_gray_score_prefix . $redis_common_gray_key;
        if (!$redis->exists($redis_gray_cache_key)) {

            // no cache
            return self::GrayNoCache($search_tels, $search_rely, $response_type);
        }
        $data_response = $redis->get($redis_gray_cache_key);
        $data_response = json_decode($data_response, true);

        // log
        $status = 0;
        $invoking_start_time = microtime(true);
        $invoking_end_time = microtime(true);
        $log_common_info = compact('status', 'invoking_start_time', 'invoking_end_time', 'search_tels', 'search_rely');
        self::recordLog($data_response, $log_common_info);

        return self::response(0, 'SUCCESS', $data_response, $response_type);
    }

    /**
     * response
     * @param int $code
     * @param string $msg
     * @param array $data
     * @param string $response_type
     * @return array|string
     */
    protected static function response($code = 0, $msg = 'SUCCESS', $data = [], $response_type = 'array')
    {
        $return_data = compact('code', 'msg', 'data');

        $response_type = strtolower($response_type);
        if (!in_array($response_type, ['array', 'json'])) {
            $response_type = 'array';
        }

        if ($response_type == 'array') {
            return $return_data;
        } else if ($response_type == 'json') {
            header("Content-Type:application/json;charset=utf-8");
            return json_encode($return_data, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * tel's gray info
     * @param $tel
     * @return mixed
     */
    protected static function getGrayInfo($tel)
    {
        $table_name = self::$col_base . ($tel % 10);
        $mysqli = GrayDb::get('gray_db');
        $sql = "select * from " . $table_name . " where tel='" . trim($tel) . "'";
        $gray_info_tel = $mysqli->dhbGetOne($sql);
        return isset($gray_info_tel['flag']) ? $gray_info_tel['flag'] : 0;
    }

    /**
     * no cache
     * @param $search_tels
     * @param $search_rely
     * @param string $response_type
     * @return array|string
     */
    protected static function GrayNoCache($search_tels, $search_rely, $response_type = 'array')
    {
        $invoking_start_time = microtime(true);

        // check params
        $search_tel = is_array($search_tels) ? $search_tels : (array)$search_tels;
        if (!$search_tel || (count($search_tel) > self::$allow_batch_num)) {
            // failed log
            $invoking_end_time = microtime(true);
            $status = '9999';
            $log_info = compact('status', 'invoking_start_time', 'invoking_end_time', 'search_rely', 'search_tels');
            self::recordLog([], $log_info);

            return self::response(1, 'FAIL', [], $response_type);
        }

        // 因为分表,所以必须单个的查询或者小批量的查询
        $data_response = [];
        foreach ($search_tel as $item_tel) {
            $flag = self::getGrayInfo((string)$item_tel);
            $data_response[$item_tel] = ['flag' => $flag];
        }

        // about log
        $status = 0;
        $invoking_end_time = microtime(true);
        $log_common_info = compact('status', 'invoking_start_time', 'invoking_end_time', 'search_rely', 'search_tels');
        self::recordLog($data_response, $log_common_info);

        // about redis
        $time_expire_redis = mt_rand(600, 1200);
        $redis = new GrayRedis();
        $redis_gray_cache_key = self::$redis_key_gray_score_prefix . md5(json_encode($search_tel));
        $redis->setex($redis_gray_cache_key, $time_expire_redis, json_encode($data_response));

        return self::response(0, 'SUCCESS', $data_response, $response_type);
    }

    /**
     * @param array $data_response ['tel' => ['flag' => 1]]
     * @param array $log_params 元素invoking_end_time,invoking_start_time,search_tels,search_rely
     */
    private static function recordLog(array $data_response, array $log_params)
    {
        $mysqli = GrayDb::get('gray_db');

        $sid = isset($log_params['search_rely']['sid']) ? $log_params['search_rely']['sid'] : '';
        $apikey = isset($log_params['search_rely']['apikey']) ? $log_params['search_rely']['apikey'] : '';
        $invoking_params = json_encode(array_merge($log_params['search_rely'], $log_params['search_tels']), JSON_UNESCAPED_UNICODE);
        $invoking_end_time = $log_params['invoking_end_time'];
        $invoking_start_time = $log_params['invoking_start_time'];

        // failed log
        if ($status = $log_params['status']) {
            $invoking_response = '传入的电话号码的数量不在限定范围内：1-' . self::$allow_batch_num;
            $log_info = compact('status', 'invoking_start_time', 'apikey', 'sid', 'invoking_params', 'invoking_end_time', 'invoking_response');
            $mysqli->dhbInsert($log_info, self::$log_table);
            return;
        }

        // success log
        $log_invoking_response = [];
        $log_response_message = ['未命中', '灰名单', '黑名单'];
        foreach ($data_response as $tel => $response) {
            $flag = $response['flag'];
            array_push($log_invoking_response, ['tel' => $tel, 'invoking_response' => $log_response_message[$flag]]);
        }

        $log_common_info = compact('status', 'invoking_start_time', 'invoking_end_time', 'apikey', 'sid', 'invoking_params');
        $log_info = array_map(function ($item) use ($log_common_info) {
            return array_merge($item, $log_common_info);
        }, $log_invoking_response);

        $mysqli->dhbBatchInsert($log_info, self::$log_table);
    }
}
