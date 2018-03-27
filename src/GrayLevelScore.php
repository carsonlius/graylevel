<?php

namespace gray\level;

class GrayLevelScore
{
    protected static $col_base = 'gray_level_score_';
    protected static $allow_batch_num = 50;

    public static function getScore($tel, $response_type = 'array')
    {
        #参数过滤
        $search_tel = is_array($tel) ? $tel : (array)trim($tel);
        if (!$search_tel) {
            self::response(1, 'FAIL', [], $response_type);
        }

        #批量限制
        if (count($search_tel) > self::$allow_batch_num) {
            self::response(1, 'FAIL', [], $response_type);
        }

        // 因为分表,所以必须单个的查询或者小批量的查询
        $data_response = [];
        foreach ($search_tel as $item_tel) {
            $gray_info_tel = self::getGrayInfo((string)$item_tel);
            $data_response[$item_tel] = [
                'flag' => isset($gray_info_tel['flag']) ? $gray_info_tel['flag'] : '未命中'
            ];
        }

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
        return $mysqli->dhbGetOne($sql);
    }
}
