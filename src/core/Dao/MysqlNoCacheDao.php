<?php

namespace CI\core\Dao;


use CI\core\Exceptions\SeniorException;

/**
 * Class mysql_Dao_no_cache
 * 不需要做缓存的Dao父类
 *
 * @package CI\core\dao
 */
class MysqlNoCacheDao extends Dao
{
    public static function fetchPkByUdx(array $udx)
    {
        self::checkUdx($udx);

        $query = self::db()->select([static::$_primary_key])->get_where(static::$_table, $udx);
        log_message('debug', self::db()->last_query());
        if ($query === false) {
            self::throwSqlErr();
        }
        if ($query->num_rows() <= 0) {
            $pk = null;
        } else {
            $result = $query->first_row('array');
            $pk = $result[static::$_primary_key];
        }

        return $pk;
    }

    public static function fetchByUdx(array $udx, array $selected = [])
    {
        self::checkUdx($udx);

        if (empty($selected)) {
            $selected = static::$_select_fields;
        } else {
            $selected = array_intersect(static::$_select_fields, $selected);
        }

        $query = self::db()->select($selected)->get_where(static::$_table, $udx);
        log_message('debug', self::db()->last_query());

        if ($query === false) {
            self::throwSqlErr();
        } elseif ($query->num_rows() <= 0) {
            return null;
        }
        $result = $query->first_row('array');

        return $result;
    }

    /**
     * @param array $udx
     * @param string $where_in_key
     * @param array $where_in_value_arr
     *
     * @return array [v1 => [pk1, pk2], v2 => [pk3, pk4]]
     * @throws \Exception
     */
    public static function findPksByUdxLastFieldWhereIn(array $udx, string $where_in_key, array $where_in_value_arr)
    {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $udx[$where_in_key] = '%s';
        self::checkUdx($udx);
        unset($udx[$where_in_key]);

        if (!empty($udx)) {
            self::db()->where($udx);
        }

        $query = self::db()->select([static::$_primary_key, $where_in_key])->where_in($where_in_key,
            $where_in_value_arr)->get(static::$_table);
        log_message('debug', self::db()->last_query());

        $res = [];
        if ($query === false) {
            self::throwSqlErr();
        } elseif ($query->num_rows() > 0) {
            $t = array_column($query->result_array(), static::$_primary_key, $where_in_key);

            foreach ($where_in_value_arr as $v) {
                if (isset($t[$v])) {
                    $res[$v] = $t[$v];
                }
            }
        }

        return $res;
    }

    /**
     * @param array $udx
     * @param string $where_in_key
     * @param array $where_in_value_arr
     * @param array $selected
     *
     * @return array [v1 => data1, v2 => data2]
     * @throws \Exception
     */
    public static function findByUdxLastFieldWhereIn(
        array $udx,
        string $where_in_key,
        array $where_in_value_arr,
        array $selected = []
    ) {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $udx[$where_in_key] = '%s';
        self::checkUdx($udx);
        unset($udx[$where_in_key]);

        if (empty($selected)) {
            $selected = static::$_select_fields;
        } else {
            $selected = array_intersect(static::$_select_fields, $selected);
        }

        if (!in_array($where_in_key, $selected)) {
            $selected[] = $where_in_key;
        }

        if (!empty($udx)) {
            self::db()->where($udx);
        }

        $query = self::db()->select($selected)->where_in($where_in_key, $where_in_value_arr)->get(static::$_table);
        log_message('debug', self::db()->last_query());


        $res = [];
        if ($query === false) {
            self::throwSqlErr();
        } elseif ($query->num_rows() > 0) {
            $t = array_column($query->result_array(), null, $where_in_key);

            foreach ($where_in_value_arr as $v) {
                if (isset($t[$v])) {
                    $res[$v] = $t[$v];
                }
            }
        }

        return $res;
    }

    public static function fetchByPk($pk, array $selected = [])
    {
        static::checkPk($pk);

        if (empty($selected)) {
            $selected = static::$_select_fields;
        } else {
            $selected = array_intersect(static::$_select_fields, $selected);
        }

        $query = self::db()->select($selected)->get_where(static::$_table, [static::$_primary_key => $pk]);
        log_message('debug', self::db()->last_query());

        if ($query === false) {
            self::throwSqlErr();
        } elseif ($query->num_rows() <= 0) {
            return null;
        }
        $result = $query->first_row('array');

        return $result;
    }

    /**
     * @param array $pks
     * @param array $selected
     *
     * @return array [ id1 => value1, id2 => value2]
     * @throws \Exception
     */
    public static function findByPks(array $pks, array $selected = [])
    {
        if (empty($pks)) {
            return [];
        }

        array_walk($pks, 'static::checkPk');

        if (empty($selected)) {
            $selected = static::$_select_fields;
        } else {
            $selected = array_intersect(static::$_select_fields, $selected);
        }

        $query = self::db()->select($selected)->where_in(static::$_primary_key, $pks)->get(static::$_table);
        log_message('debug', self::db()->last_query());

        $res = [];
        if ($query === false) {
            self::throwSqlErr();
        } elseif ($query->num_rows() > 0) {
            $temp = array_column($query->result_array(), null, static::$_primary_key);
            //调整结果顺序，对应传入ids的顺序
            foreach ($pks as $v) {
                isset($temp[$v]) && $res[$v] = $temp[$v];
            }
        }

        return $res;
    }

    protected static function _countByIdx(array $idx = [], $tpl = null)
    {
        isset($tpl) || self::checkIdxNotMatchSort($idx);

        if (!empty($idx)) {
            self::db()->where($idx);
        }
        $count = self::db()->count_all_results(static::$_table);
        log_message('debug', self::db()->last_query());

        return $count;
    }

    public static function countByIdx(array $idx = [])
    {
        return self::_countByIdx($idx);
    }

    /**
     * @param array $idx
     * @param string $where_in_key
     * @param array $where_in_value_arr
     *
     * @return array [v1 => int1, v2 => int2]
     * @throws SeniorException
     */
    public static function countMultiByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr)
    {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxNotMatchSort($idx);
        unset($idx[$where_in_key]);

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $query = self::db()->select([$where_in_key, 'count(*) as numrows'])->where_in($where_in_key,
            $where_in_value_arr)->group_by($where_in_key)->get(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($query === false) {
            self::throwSqlErr();
        }

        $res = [];
        if ($query->num_rows() <= 0) {
            foreach ($where_in_value_arr as $v) {
                $res[$v[$where_in_key]] = 0;
            }
        } else {
            $t = array_column($query->result_array(), 'numrows', $where_in_key);

            foreach ($where_in_value_arr as $v) {
                $res[$v] = intval($t[$v] ?? 0);
            }
        }

        return $res;
    }

    /**
     * @param array $idx
     * @param array $sort
     * @param array $options ['per_page'=>10, 'page'=>1, 'selected' => []]
     *
     * @return array [ total => 2, data => [ id1 => [], id2 => []]]
     * @throws \Exception
     */
    public static function findByIdx(array $idx = [], array $sort = [], array $options = [])
    {
        $res = [
            'total' => 0,
            'data' => [],
        ];

        $tpl = self::checkIdxMatchSort($idx, $sort);
        $res['idx'] = $idx;
        $res['sort'] = $sort;

        if (!empty($options['per_page'])) {
            $res['total'] = self::_countByIdx($idx, $tpl);
            $res['per_page'] = max(1, intval($options['per_page']));

            if (empty($options['page'])) {
                $res['current_page'] = 1;
            } else {
                $res['current_page'] = max(1, intval($options['page']));
            }

            $offset = ($res['current_page'] - 1) * $res['per_page'];
            if ($offset >= $res['total']) {
                return $res;
            }

            self::db()->limit($res['per_page'], $offset);
        }

        if (empty($options['selected'])) {
            $selected = static::$_select_fields;
        } else {
            is_array($options['selected']) || $options['selected'] = explode(',', $options['selected']);
            $selected = array_intersect(static::$_select_fields, $options['selected']);
        }


        if (!empty($sort)) {
            foreach ($sort as $k => $v) {
                $s[] = $k . ' ' . $v;
            }
            self::db()->order_by(implode(',', $s));
        }

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $query = self::db()->select($selected)->get(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($query === false) {
            self::throwSqlErr();
        } elseif ($query->num_rows()) {
            $res['data'] = array_column($query->result_array(), null, static::$_primary_key);
            empty($res['per_page']) && $res['total'] = $query->num_rows();
        }

        return $res;
    }

    /**
     * @param array $idx
     * @param array $sort
     * @param array $options
     *
     * @return array array [ total => 20, data => [ id1, id2 ]]
     * @throws \Exception
     */
    public static function findPksByIdx(array $idx = [], array $sort = [], array $options = [])
    {
        $res = [
            'total' => 0,
            'data' => [],
        ];

        $tpl = self::checkIdxMatchSort($idx, $sort);
        $res['idx'] = $idx;
        $res['sort'] = $sort;

        if (!empty($options['per_page'])) {
            $res['total'] = self::_countByIdx($idx, $tpl);
            $res['per_page'] = max(1, intval($options['per_page']));

            if (empty($options['page'])) {
                $res['current_page'] = 1;
            } else {
                $res['current_page'] = max(1, intval($options['page']));
            }

            $offset = ($res['current_page'] - 1) * $res['per_page'];
            if ($offset >= $res['total']) {
                return $res;
            }

            self::db()->limit($res['per_page'], $offset);
        }

        if (!empty($sort)) {
            foreach ($sort as $k => $v) {
                $s[] = $k . ' ' . $v;
            }
            self::db()->order_by(implode(',', $s));
        }

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $query = self::db()->select([static::$_primary_key])->get(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($query === false) {
            self::throwSqlErr();
        } elseif ($query->num_rows()) {
            $res['data'] = array_column($query->result_array(), static::$_primary_key);
            empty($res['per_page']) && $res['total'] = $query->num_rows();
        }

        return $res;
    }

    /**
     * @param array $idx
     * @param string $where_in_key
     * @param array $where_in_value_arr
     * @param array $sort
     *
     * @return array [v1 => [pk1, pk2], v2 => [pk3, pk4]]
     * @throws \Exception
     */
    public static function findMultiPksByIdxLastFieldWhereIn(
        array $idx,
        string $where_in_key,
        array $where_in_value_arr,
        array $sort = []
    ) {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxMatchSort($idx, $sort);
        unset($idx[$where_in_key]);

        if (!empty($sort)) {
            $s[] = $where_in_key . ' ' . reset($sort);//加上这个是为了让索引不走filesort
            foreach ($sort as $k => $v) {
                $s[] = $k . ' ' . $v;
            }

            self::db()->order_by(implode(',', $s));
        }

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $query = self::db()->select([static::$_primary_key, $where_in_key])->where_in($where_in_key,
            $where_in_value_arr)->get(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($query === false) {
            self::throwSqlErr();
        }

        $res = [];
        if ($query->num_rows() <= 0) {
            foreach ($where_in_value_arr as $v) {
                $res[$v] = [];
            }
        } else {
            $t = [];
            foreach ($query->result_array() as $v) {
                $t[$v[$where_in_key]][] = $v[static::$_primary_key];
            }

            foreach ($where_in_value_arr as $v) {
                $res[$v] = $t[$v] ?? [];
            }
        }

        return $res;
    }

    /**
     * @param array $idx
     * @param string $where_in_key
     * @param array $where_in_value_arr
     * @param array $sort
     * @param array $selected
     *
     * @return array [v1 => [pk1 => data1, pk2 => data2], v2 => [pk3 => data3, pk4 => data4]]
     * @throws \Exception
     */
    public static function findMultiByIdxLastFieldWhereIn(
        array $idx,
        string $where_in_key,
        array $where_in_value_arr,
        array $sort = [],
        array $selected = []
    ) {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxMatchSort($idx, $sort);
        unset($idx[$where_in_key]);

        if (!empty($sort)) {
            foreach ($sort as $k => $v) {
                $s[] = $k . ' ' . $v;
            }

            array_unshift($s, $where_in_key . ' ' . reset($sort));
            self::db()->order_by(implode(',', $s));
        }

        if (empty($selected)) {
            $selected = static::$_select_fields;
        } else {
            $selected = array_intersect(static::$_select_fields, $selected);
        }


        if (!in_array($where_in_key, $selected)) {
            $selected[] = $where_in_key;
        }

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $query = self::db()->select($selected)->where_in($where_in_key, $where_in_value_arr)->get(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($query === false) {
            self::throwSqlErr();
        }

        $res = [];
        if ($query->num_rows() <= 0) {
            foreach ($where_in_value_arr as $v) {
                $res[$v] = [];
            }
        } else {
            $t = [];
            foreach ($query->result_array() as $v) {
                $t[$v[$where_in_key]][] = $v;
            }

            foreach ($where_in_value_arr as $v) {
                $res[$v] = $t[$v] ?? [];
            }
        }

        return $res;
    }

    public static function insert(array $param)
    {
        self::checkInsertPk($param);
        $new_param = array_intersect_key($param, static::$_insert_fields) + static::$_insert_fields;

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();
        isset(static::$_created_time_key) && $new_param[static::$_created_time_key] = time();

        $res = self::db()->insert(static::$_table, $new_param);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return static::$_auto_pk ? self::db()->insert_id() : $new_param[static::$_primary_key];
    }

    public static function insertBatch(array $params)
    {
        if (empty($params)) {
            return 0;
        }

        foreach ($params as $k => &$v) {
            self::checkInsertPk($v);
            $v = array_intersect_key($v, static::$_insert_fields) + static::$_insert_fields;
            isset(static::$_updated_time_key) && $v[static::$_updated_time_key] = time();
            isset(static::$_created_time_key) && $v[static::$_created_time_key] = time();
        }
        unset($v);

        $res = self::db()->insertBatch(static::$_table, $params);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function updateByPk(array $param, $pk)
    {
        static::checkPk($pk);

        $new_param = array_intersect_key($param, static::$_update_fields);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->update(static::$_table, $new_param, [static::$_primary_key => $pk]);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function updateByUdx(array $param, array $udx)
    {
        self::checkUdx($udx);

        $new_param = array_intersect_key($param, static::$_update_fields);
        $new_param = array_diff_assoc($new_param, $udx);
        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->update(static::$_table, $new_param, $udx);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function updateByUdxLastFieldWhereIn(
        array $param,
        array $udx,
        string $where_in_key,
        array $where_in_value_arr
    ) {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $udx[$where_in_key] = '%s';
        self::checkUdx($udx);
        unset($udx[$where_in_key]);

        $new_param = array_intersect_key($param, static::$_update_fields);
        $new_param = array_diff_assoc($new_param, $udx);
        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();

        if (!empty($udx)) {
            self::db()->where($udx);
        }

        $res = self::db()->where_in($where_in_key, $where_in_value_arr)->update(static::$_table, $new_param);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function updateByIdx(array $param, array $idx)
    {
        self::checkIdxNotMatchSort($idx);

        $new_param = array_intersect_key($param, static::$_update_fields);
        $new_param = array_diff_assoc($new_param, $idx);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $res = self::db()->update(static::$_table, $new_param);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }


        return self::db()->affected_rows();
    }

    public static function updateByIdxLastFieldWhereIn(
        array $param,
        array $idx,
        string $where_in_key,
        array $where_in_value_arr
    ) {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxNotMatchSort($idx);
        unset($idx[$where_in_key]);

        $new_param = array_intersect_key($param, static::$_update_fields);
        $new_param = array_diff_assoc($new_param, $idx);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $res = self::db()->where_in($where_in_key, $where_in_value_arr)->update(static::$_table, $new_param);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }


        return self::db()->affected_rows();
    }

    public static function updateByPks(array $param, array $pks)
    {
        if (empty($pks) || empty($param)) {
            return 0;
        }

        array_walk($pks, 'static::checkPk');

        $new_param = array_intersect_key($param, static::$_update_fields);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->where_in(static::$_primary_key, $pks)->update(static::$_table, $new_param);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function deleteByPk($pk)
    {
        static::checkPk($pk);

        $res = self::db()->delete(static::$_table, [static::$_primary_key => $pk]);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function deleteByUdx(array $udx)
    {
        self::checkUdx($udx);

        $res = self::db()->delete(static::$_table, $udx);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function deleteByIdx(array $idx)
    {
        self::checkIdxNotMatchSort($idx);

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $res = self::db()->delete(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }


        return self::db()->affected_rows();
    }

    public static function deleteByPks(array $pks)
    {
        if (empty($pks)) {
            return 0;
        }

        array_walk($pks, 'static::checkPk');

        $res = self::db()->where_in(static::$_primary_key, $pks)->delete(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    /**
     * @param array $udx
     * @param string $where_in_key
     * @param array $where_in_value_arr
     *
     * @return int
     * @throws \Exception
     */
    public static function deleteByUdxLastFieldWhereIn(array $udx, string $where_in_key, array $where_in_value_arr)
    {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $udx[$where_in_key] = '%s';
        self::checkUdx($udx);
        unset($udx[$where_in_key]);

        if (!empty($udx)) {
            self::db()->where($udx);
        }

        $res = self::db()->where_in($where_in_key, $where_in_value_arr)->delete(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    /**
     * @param array $idx
     * @param string $where_in_key
     * @param array $where_in_value_arr
     *
     * @return int
     * @throws \Exception
     */
    public static function deleteByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr)
    {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxNotMatchSort($idx);
        unset($idx[$where_in_key]);

        if (!empty($idx)) {
            self::db()->where($idx);
        }

        $res = self::db()->where_in($where_in_key, $where_in_value_arr)->delete(static::$_table);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->affected_rows();
    }

    public static function insertDuplicateUpdate(array $insert, array $update)
    {
        self::checkInsertPk($insert);
        $new_insert_param = array_intersect_key($insert, static::$_insert_fields) + static::$_insert_fields;

        isset(static::$_updated_time_key) && $new_insert_param[static::$_updated_time_key] = time();
        isset(static::$_created_time_key) && $new_insert_param[static::$_created_time_key] = time();

        $key_insert = array_keys($new_insert_param);
        $binds[] = array_values($new_insert_param);

        $new_update_param = array_intersect_key($update, static::$_update_fields);
        isset(static::$_updated_time_key) && $new_update_param[static::$_updated_time_key] = time();

        if (empty($new_update_param)) {
            return false;
        }

        $key_update = array_keys($new_update_param);
        $binds = array_merge($binds, array_values($new_update_param));

        $sql = 'INSERT INTO ' . static::$_table .
            ' (`' . implode('`,`', $key_insert) . '`)' .
            ' VALUES ? ON DUPLICATE KEY UPDATE `' .
            implode('` = ?, `', $key_update) .
            '` = ?;';

        $res = self::db()->query($sql, $binds);

        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        return self::db()->insert_id();
    }
}
