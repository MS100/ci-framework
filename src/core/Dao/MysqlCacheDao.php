<?php

namespace CI\core\Dao;

use CI\core\CacheAppend;
use CI\core\Exceptions\JuniorException;
use CL\Common\Error;
use CI\core\Exceptions\SeniorException;

/**
 * Class mysql_Dao_cache
 * 需要做缓存的Dao父类
 *
 * @package CI\core\dao
 */
class MysqlCacheDao extends Dao
{
    protected static $_cache_group = '';//使用的缓存配置，config/cache.php里的数组键名

    final protected static function getPkCacheKey($primary_key)
    {
        return CacheAppend::getAppend(static::$_table).sprintf(
                '%s:%s',
                static::$_table,
                $primary_key
            );
    }

    final protected static function getIdxCacheKey(array $tpl, array $idx)
    {
        foreach ($tpl['where'] as $m => $n) {
            $tpl['where'][$m] = is_null($idx[$m]) ? $idx[$m]
                : (string)$idx[$m];//这里转成字符串是为了防止整型和字符串型做出来的key不一样
        }

        return CacheAppend::getAppend(static::$_table).sprintf(
                '%s:%s',
                static::$_table,
                json_encode($tpl, JSON_UNESCAPED_UNICODE)
            );
    }

    final protected static function getIdxCountCacheKey(array $tpl, array $idx)
    {
        $t = [];
        foreach ($tpl['where'] as $m => $n) {
            $t['where'][$m] = is_null($idx[$m]) ? $idx[$m] : (string)$idx[$m];
        }

        return CacheAppend::getAppend(static::$_table).
            sprintf(
                '%s:count%s',
                static::$_table,
                json_encode($t, JSON_UNESCAPED_UNICODE)
            );
    }

    final protected static function getUdxCacheKey(array $tpl, array $udx)
    {
        $temp = [];
        foreach ($tpl as $m => $n) {
            $temp[$m] = is_null($udx[$m]) ? $udx[$m] : (string)$udx[$m];
        }

        return CacheAppend::getAppend(static::$_table).sprintf(
                '%s:%s',
                static::$_table,
                json_encode($temp, JSON_UNESCAPED_UNICODE)
            );
    }

    public static function fetchPkByUdx(array $udx)
    {
        $tpl = self::checkUdx($udx);
        $cache_key = self::getUdxCacheKey($tpl, $udx);

        log_message('debug', __METHOD__.' get udx cache key: '.$cache_key);
        $pk = self::cache()->get($cache_key, false);

        if ($pk === false) {
            $query = self::db()->select([static::$_primary_key])->get_where(
                static::$_table,
                $udx
            );
            log_message('debug', self::db()->last_query());
            if ($query === false) {
                self::throwSqlErr();
            }
            if ($query->num_rows() <= 0) {
                $pk = null;
            } else {
                $result = $query->first_row('array');
                /*$pk_cache_key = self::getPkCacheKey($result[static::$_primary_key]);
                log_message('debug', __METHOD__ . ' add pk cache key: ' . $pk_cache_key);
                self::cache()->add($pk_cache_key, $result);*/
                $pk = $result[static::$_primary_key];
            }

            log_message('debug', __METHOD__.' add udx cache key: '.$cache_key);
            self::cache()->add($cache_key, $pk);
        }

        return $pk;
    }

    public static function fetchByUdx(array $udx, array $selected = [])
    {
        $pk = self::fetchPkByUdx($udx);

        if ($pk) {
            $data = self::fetchByPk($pk, $selected);
        } else {
            $data = null;
        }

        return $data;
    }

    /**
     * @param array  $udx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
     *
     * @return array [v1 => pk1, v2 => pk2]]
     * @throws \Exception
     */
    public static function findPksByUdxLastFieldWhereIn(
        array $udx,
        string $where_in_key,
        array $where_in_value_arr
    ) {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $udx[$where_in_key] = '%s';
        $tpl = self::checkUdx($udx);
        $cache_key_tpl = self::getUdxCacheKey($tpl, $udx);

        $cache_keys = [];
        foreach ($where_in_value_arr as $value) {
            $udx[$where_in_key] = $value;
            $cache_keys[] = self::getUdxCacheKey($tpl, $udx);
        }

        log_message(
            'debug',
            __METHOD__.' get multi udx cache keys: '.var_export(
                $cache_keys,
                true
            )
        );
        $cache_results = self::cache()->getMultiple($cache_keys, false);

        $exists_where_in = [];
        $temp = [];
        foreach ($cache_results as $k => $v) {
            list($t) = sscanf($k, $cache_key_tpl);
            if ($v !== false) {
                $exists_where_in[] = $t;
                $temp[$t] = $v;
            }
        }

        $not_exists_where_in = array_diff(
            $where_in_value_arr,
            $exists_where_in
        );

        if (!empty($not_exists_where_in)) {
            unset($udx[$where_in_key]);

            if (!empty($udx)) {
                self::db()->where($udx);
            }
            $query = self::db()->select([static::$_primary_key, $where_in_key])
                ->where_in(
                    $where_in_key,
                    $not_exists_where_in
                )->get(static::$_table);
            log_message('debug', self::db()->last_query());

            if ($query === false) {
                self::throwSqlErr();
            }

            $not_exists_results = [];
            if ($query->num_rows() <= 0) {
                foreach ($not_exists_where_in as $v) {
                    $udx[$where_in_key] = $v;
                    $temp[$v]
                        = $not_exists_results[self::getUdxCacheKey($tpl, $udx)]
                        = null;
                }
            } else {
                $t = array_column(
                    $query->result_array(),
                    static::$_primary_key,
                    $where_in_key
                );

                foreach ($not_exists_where_in as $v) {
                    $udx[$where_in_key] = $v;
                    $temp[$v]
                        = $not_exists_results[self::getUdxCacheKey($tpl, $udx)]
                        = ($t[$v] ?? null);
                }
            }
            self::cache()->setMultiple($not_exists_results);
            log_message(
                'debug',
                __METHOD__.' set multi udx cache keys: '.var_export(
                    $not_exists_results,
                    true
                )
            );
        }

        $res = [];
        foreach ($where_in_value_arr as $v) {
            if (isset($temp[$v])) {
                $res[$v] = $temp[$v];
            }
        }

        return $res;
    }

    /**
     * @param array  $udx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
     * @param array  $selected
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
        $pks = self::findPksByUdxLastFieldWhereIn(
            $udx,
            $where_in_key,
            $where_in_value_arr
        );
        $res = [];
        if (empty($pks)) {
            return $res;
        }

        $data = self::findByPks($pks, $selected ?? []);
        foreach ($pks as $k => $pk) {
            if (isset($data[$pk])) {
                $res[$k] = $data[$pk];
            }
        }

        return $res;
    }

    public static function fetchByPk($pk, array $selected = [])
    {
        static::checkPk($pk);

        $cache_key = self::getPkCacheKey($pk);
        $result = self::cache()->get($cache_key, false);
        log_message('debug', __METHOD__.' get pk cache key: '.$cache_key);


        if ($result === false) {
            $query = self::db()->select(static::$_select_fields)->get_where(
                static::$_table,
                [static::$_primary_key => $pk]
            );
            log_message('debug', self::db()->last_query());

            if ($query === false) {
                self::throwSqlErr();
            }
            if ($query->num_rows() <= 0) {
                $result = null;
            } else {
                $result = $query->first_row('array');
            }
            self::cache()->add($cache_key, $result);
            log_message('debug', __METHOD__.' add pk cache key: '.$cache_key);
        }

        if (!empty($selected) && !empty($result)) {
            $result = array_intersect_key($result, array_flip($selected));
        }

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

        $cache_keys = array_map('self::getPkCacheKey', $pks);

        log_message(
            'debug',
            __METHOD__.' get multi pk cache keys: '.var_export(
                $cache_keys,
                true
            )
        );

        $cache_results = self::cache()->getMultiple($cache_keys, false);

        $cache_key_tpl = self::getPkCacheKey('%s');

        $exists_pks = [];
        $temp = [];
        foreach ($cache_results as $k => $v) {
            list($pk) = sscanf($k, $cache_key_tpl);
            if ($v !== false) {
                $exists_pks[] = $pk;
                $temp[$pk] = $v;
            }
        }

        $not_exists_pks = array_diff($pks, $exists_pks);


        if (!empty($not_exists_pks)) {
            $query = self::db()->select(static::$_select_fields)->where_in(
                static::$_primary_key,
                $not_exists_pks
            )->get(static::$_table);
            log_message('debug', self::db()->last_query());

            if ($query === false) {
                self::throwSqlErr();
            }

            $not_exists_results = [];
            if ($query->num_rows() <= 0) {
                foreach ($not_exists_pks as $pk) {
                    $temp[$pk]
                        = $not_exists_results[self::getPkCacheKey($pk)]
                        = null;
                }
            } else {
                $t = array_column(
                    $query->result_array(),
                    null,
                    static::$_primary_key
                );
                foreach ($not_exists_pks as $pk) {
                    $temp[$pk]
                        = $not_exists_results[self::getPkCacheKey($pk)]
                        = ($t[$pk] ?? null);
                }
            }
            self::cache()->setMultiple($not_exists_results);
            log_message(
                'debug',
                __METHOD__.' set multi pk cache keys: '.var_export(
                    $not_exists_results,
                    true
                )
            );
        }

        $res = [];
        if (!empty($selected)) {
            $selected = array_flip($selected);
            foreach ($pks as $pk) {
                if (!empty($temp[$pk])) {
                    $res[$pk] = array_intersect_key($temp[$pk], $selected);
                }
            }
        } else {
            foreach ($pks as $pk) {
                if (!empty($temp[$pk])) {
                    $res[$pk] = $temp[$pk];
                }
            }
        }

        return $res;
    }

    protected static function _countByIdx(array $idx = [], $tpl = null)
    {
        isset($tpl) || $tpl = self::checkIdxNotMatchSort($idx);
        $count_cache_key = self::getIdxCountCacheKey($tpl, $idx);
        $count = self::cache()->get($count_cache_key, false);
        log_message(
            'debug',
            __METHOD__.' get idx count cache key: '.$count_cache_key
        );

        if ($count === false) {
            if (!empty($idx)) {
                self::db()->where($idx);
            }
            $count = self::db()->count_all_results(static::$_table);
            log_message('debug', self::db()->last_query());
            self::cache()->add($count_cache_key, intval($count));
            log_message(
                'debug',
                __METHOD__.' add idx count cache key: '.$count_cache_key
            );
        }

        return $count;
    }

    public static function countByIdx(array $idx = [])
    {
        return static::_countByIdx($idx);
    }

    /**
     * @param array  $idx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
     *
     * @return array [v1 => int1, v2 => int2]
     * @throws SeniorException
     */
    public static function countMultiByIdxLastFieldWhereIn(
        array $idx,
        string $where_in_key,
        array $where_in_value_arr
    ) {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $idx[$where_in_key] = '%s';
        $tpl = self::checkIdxNotMatchSort($idx);

        $cache_key_tpl = self::getIdxCountCacheKey($tpl, $idx);
        $cache_keys = [];
        foreach ($where_in_value_arr as $value) {
            $idx[$where_in_key] = $value;
            $cache_keys[] = self::getIdxCountCacheKey($tpl, $idx);
        }

        log_message(
            'debug',
            __METHOD__.' get multi idx count cache keys: '.var_export(
                $cache_keys,
                true
            )
        );
        $cache_results = self::cache()->getMultiple($cache_keys, false);

        $exists_where_in = [];
        $temp = [];
        foreach ($cache_results as $k => $v) {
            list($t) = sscanf($k, $cache_key_tpl);
            if ($v !== false) {
                $exists_where_in[] = $t;
                $temp[$t] = $v;
            }
        }

        $not_exists_where_in = array_diff(
            $where_in_value_arr,
            $exists_where_in
        );

        if (!empty($not_exists_where_in)) {
            unset($idx[$where_in_key]);

            if (!empty($idx)) {
                self::db()->where($idx);
            }
            $query = self::db()->select([$where_in_key, 'count(*) as numrows'])
                ->where_in(
                    $where_in_key,
                    $not_exists_where_in
                )->group_by($where_in_key)->get(static::$_table);
            log_message('debug', self::db()->last_query());

            if ($query === false) {
                self::throwSqlErr();
            }

            $not_exists_results = [];
            if ($query->num_rows() <= 0) {
                foreach ($where_in_value_arr as $v) {
                    $idx[$where_in_key] = $v;
                    $temp[$v]
                        = $not_exists_results[self::getIdxCacheKey($tpl, $idx)]
                        = 0;
                }
            } else {
                $t = array_column(
                    $query->result_array(),
                    'numrows',
                    $where_in_key
                );

                foreach ($not_exists_where_in as $v) {
                    $idx[$where_in_key] = $v;
                    $temp[$v]
                        = $not_exists_results[self::getIdxCacheKey($tpl, $idx)]
                        = intval($t[$v] ?? 0);
                }
            }
            self::cache()->setMultiple($not_exists_results);
            log_message(
                'debug',
                __METHOD__.' set multi idx count cache keys: '.var_export(
                    $not_exists_results,
                    true
                )
            );
        }

        $res = [];
        foreach ($where_in_value_arr as $v) {
            $res[$v] = $temp[$v] ?? 0;
        }

        return $res;
    }

    /**
     * @param array $idx
     * @param array $sort
     * @param array $options ['per_page'=>2, 'page'=>1, 'selected' => []]
     *
     * @return array [ current_page => 1, per_page => 2, total => 10, data => [ id1 => [], id2 => []]]
     * @throws \Exception
     */
    public static function findByIdx(
        array $idx = [],
        array $sort = [],
        array $options = []
    ) {
        $res = self::findPksByIdx($idx, $sort, $options);

        if (count($res['data']) > 0) {
            $res['data'] = self::findByPks(
                $res['data'],
                $options['selected'] ?? []
            );
        }

        return $res;
    }

    /**
     * @param array $idx
     * @param array $sort
     * @param array $options
     *
     * @return array [ num => 2, list => [ id1, id2 ]]
     * @throws \Exception
     */
    public static function findPksByIdx(
        array $idx = [],
        array $sort = [],
        array $options = []
    ) {
        $res = [
            'total' => 0,
            'data'  => [],
        ];
        $tpl = self::checkIdxMatchSort($idx, $sort);
        $res['idx'] = $idx;
        $res['sort'] = $sort;

        if (!empty($tpl['limit']) || !empty($options['per_page'])) {
            $count = static::_countByIdx($idx, $tpl);
        }

        if (!empty($options['per_page'])) {
            $res['total'] = $count;
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
        }

        if (empty($tpl['limit']) || $count <= $tpl['limit']
            || (isset($offset, $res['per_page'])
                && $offset + $res['per_page'] <= $tpl['limit'])
        ) {
            $cache_key = self::getIdxCacheKey($tpl, $idx);
            $pks = self::cache()->get($cache_key, false);
            log_message('debug', __METHOD__.' get idx cache key: '.$cache_key);

            if ($pks === false) {
                if (!empty($sort)) {
                    foreach ($sort as $k => $v) {
                        $s[] = $k.' '.$v;
                    }
                    self::db()->order_by(implode(',', $s));
                }

                if (!empty($tpl['limit']) && $count > $tpl['limit']) {
                    self::db()->limit($tpl['limit']);
                }

                $query = self::db()->select([static::$_primary_key])->get_where(
                    static::$_table,
                    $idx
                );
                log_message('debug', self::db()->last_query());

                if ($query === false) {
                    self::throwSqlErr();
                }
                if ($query->num_rows() <= 0) {
                    $pks = [];
                } else {
                    $pks = array_column(
                        $query->result_array(),
                        static::$_primary_key
                    );
                }
                self::cache()->add($cache_key, $pks);
                log_message(
                    'debug',
                    __METHOD__.' add idx cache key: '.$cache_key
                );
            }

            if (isset($offset, $res['per_page'])) {
                $pks = array_slice($pks, $offset, $res['per_page']);
            }

            if (!empty($pks)) {
                $res['data'] = $pks;
                empty($res['per_page']) && $res['total'] = count($res['data']);
            }
        } else {
            if (isset($offset, $res['per_page'])) {
                self::db()->limit($res['per_page'], $offset);
            }

            if (!empty($sort)) {
                foreach ($sort as $k => $v) {
                    $s[] = $k.' '.$v;
                }
                self::db()->order_by(implode(',', $s));
            }

            if (!empty($idx)) {
                self::db()->where($idx);
            }

            $query = self::db()->select([static::$_primary_key])->get(
                static::$_table
            );
            log_message('debug', self::db()->last_query());
            if ($query === false) {
                self::throwSqlErr();
            }

            if ($query->num_rows()) {
                $res['data'] = array_column(
                    $query->result_array(),
                    static::$_primary_key
                );
                empty($res['per_page']) && $res['total'] = $query->num_rows();
            }
        }

        return $res;
    }

    /**
     * @param array  $idx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
     * @param array  $sort
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
        $tpl = self::checkIdxMatchSort($idx, $sort);
        if (!empty($tpl['limit'])) {
            throw new JuniorException(new Error('-1', '使用此方法的idx不能现有limit限制'));
        }

        $cache_key_tpl = self::getIdxCacheKey($tpl, $idx);
        $cache_keys = [];
        foreach ($where_in_value_arr as $value) {
            $idx[$where_in_key] = $value;
            $cache_keys[] = self::getIdxCacheKey($tpl, $idx);
        }

        log_message(
            'debug',
            __METHOD__.' get multi idx cache keys: '.var_export(
                $cache_keys,
                true
            )
        );
        $cache_results = self::cache()->getMultiple($cache_keys, false);

        $exists_where_in = [];
        $temp = [];
        foreach ($cache_results as $k => $v) {
            list($t) = sscanf($k, $cache_key_tpl);
            if ($v !== false) {
                $exists_where_in[] = $t;
                $temp[$t] = $v;
            }
        }

        $not_exists_where_in = array_diff(
            $where_in_value_arr,
            $exists_where_in
        );

        if (!empty($not_exists_where_in)) {
            unset($idx[$where_in_key]);

            if (!empty($sort)) {
                foreach ($sort as $k => $v) {
                    $s[] = $k.' '.$v;
                }

                array_unshift($s, $where_in_key.' '.reset($sort));
                self::db()->order_by(implode(',', $s));
            }

            if (!empty($idx)) {
                self::db()->where($idx);
            }

            $query = self::db()->select([static::$_primary_key, $where_in_key])
                ->where_in(
                    $where_in_key,
                    $not_exists_where_in
                )->get(static::$_table);
            log_message('debug', self::db()->last_query());

            if ($query === false) {
                self::throwSqlErr();
            }

            $not_exists_results = [];
            if ($query->num_rows() <= 0) {
                foreach ($not_exists_where_in as $v) {
                    $idx[$where_in_key] = $v;
                    $temp[$v]
                        = $not_exists_results[self::getIdxCacheKey($tpl, $idx)]
                        = [];
                }
            } else {
                $t = [];
                foreach ($query->result_array() as $v) {
                    $t[$v[$where_in_key]][] = $v[static::$_primary_key];
                }

                foreach ($not_exists_where_in as $v) {
                    $idx[$where_in_key] = $v;
                    $temp[$v]
                        = $not_exists_results[self::getIdxCacheKey($tpl, $idx)]
                        = $t[$v] ?? [];
                }
            }
            self::cache()->setMultiple($not_exists_results);
            log_message(
                'debug',
                __METHOD__.' set multi idx cache keys: '.var_export(
                    $not_exists_results,
                    true
                )
            );
        }

        $res = [];
        foreach ($where_in_value_arr as $v) {
            $res[$v] = $temp[$v] ?? [];
        }

        return $res;
    }

    /**
     * @param array  $idx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
     * @param array  $sort
     * @param array  $selected
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
        $multi_pks = self::findMultiPksByIdxLastFieldWhereIn(
            $idx,
            $where_in_key,
            $where_in_value_arr,
            $sort
        );
        $res = [];
        if (empty($multi_pks)) {
            return $res;
        }

        $pks = array_merge(...$multi_pks);

        $data = self::findByPks($pks, $selected ?? []);
        foreach ($multi_pks as $k => $v) {
            if (empty($v)) {
                $res[$k] = [];
            } else {
                foreach ($v as $pk) {
                    if (isset($data[$pk])) {
                        $res[$k][$pk] = $data[$pk];
                    } elseif (!isset($res[$k])) {
                        $res[$k] = [];
                    }
                }
            }
        }

        return $res;
    }

    public static function insert(array $param)
    {
        self::checkInsertPk($param);
        $new_param = array_intersect_key($param, static::$_insert_fields)
            + static::$_insert_fields;

        isset(static::$_updated_time_key)
        && $new_param[static::$_updated_time_key] = time();
        isset(static::$_created_time_key)
        && $new_param[static::$_created_time_key] = time();

        $res = self::db()->insert(static::$_table, $new_param);
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        static::$_auto_pk
        && $new_param[static::$_primary_key] = self::db()->insert_id();
        self::flushInsertCache($new_param);

        return $new_param[static::$_primary_key];
    }

    public static function insertBatch(array $params)
    {
        if (empty($params)) {
            return 0;
        }

        foreach ($params as $k => &$v) {
            self::checkInsertPk($v);
            $v = array_intersect_key($v, static::$_insert_fields)
                + static::$_insert_fields;
            isset(static::$_updated_time_key)
            && $v[static::$_updated_time_key] = time();
            isset(static::$_created_time_key)
            && $v[static::$_created_time_key] = time();
        }

        unset($v);
        $res = self::db()->insertBatch(
            static::$_table,
            $params,
            null,
            count($params)
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        if (static::$_auto_pk) {
            $start_primary_key = self::db()->insert_id();
            foreach ($params as $k => &$v) {
                $v[static::$_primary_key] = $start_primary_key++;
            }
            unset($v);
        }

        self::flushInsertBatchCache($params);

        return self::db()->affected_rows();
    }

    public static function updateByPk(array $param, $pk)
    {
        static::checkPk($pk);

        $new_param = array_intersect_key($param, static::$_update_fields);

        if (empty($new_param)) {
            return 0;
        }

        if (empty(static::$_idx) && empty(static::$_udx)) {
            $old_data = [static::$_primary_key => $pk];
        } else {
            $old_data = self::fetchByPk($pk);

            if (empty($old_data)) {
                return 0;
            }

            $new_param = array_diff_assoc($new_param, $old_data);
            if (empty($new_param)) {
                return 0;
            }
        }

        isset(static::$_updated_time_key)
        && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->update(
            static::$_table,
            $new_param,
            [static::$_primary_key => $pk]
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushUpdateCache($new_param, $old_data);

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

        $old_data = self::fetchByUdx($udx);

        if (empty($old_data)) {
            return 0;
        }

        $new_param = array_diff_assoc($new_param, $old_data);
        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key)
        && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->update(
            static::$_table,
            $new_param,
            [static::$_primary_key => $old_data[static::$_primary_key]]
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushUpdateCache($new_param, $old_data);

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


        $pks = self::findPksByUdxLastFieldWhereIn(
            $udx,
            $where_in_key,
            $where_in_value_arr
        );
        if (empty($pks)) {
            return 0;
        }
        $old_data_arr = self::findByPks($pks);

        isset(static::$_updated_time_key)
        && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->where_in(static::$_primary_key, $pks)->update(
            static::$_table,
            $new_param
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushUpdateBatchCache($new_param, $old_data_arr);

        return self::db()->affected_rows();
    }

    public static function updateByIdx(array $param, array $idx)
    {
        $tpl = self::checkIdxNotMatchSort($idx);

        $new_param = array_intersect_key($param, static::$_update_fields);
        $new_param = array_diff_assoc($new_param, $idx);

        if (empty($new_param)) {
            return 0;
        }

        $old_pks = self::findPksByIdx($idx, $tpl['sort']);
        if (empty($old_pks['total'])) {
            return 0;
        }

        $pks = $old_pks['data'];
        $old_data_arr = self::findByPks($pks);

        isset(static::$_updated_time_key)
        && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->where_in(static::$_primary_key, $pks)->update(
            static::$_table,
            $new_param
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushUpdateBatchCache($new_param, $old_data_arr);

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

        $pks = self::findMultiPksByIdxLastFieldWhereIn(
            $idx,
            $where_in_key,
            $where_in_value_arr
        );
        $pks = array_merge(...$pks);
        if (empty($pks)) {
            return 0;
        }
        $old_data_arr = self::findByPks($pks);

        isset(static::$_updated_time_key)
        && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->where_in(static::$_primary_key, $pks)->update(
            static::$_table,
            $new_param
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushUpdateBatchCache($new_param, $old_data_arr);

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

        $old_data_arr = self::findByPks($pks);
        if (empty($old_data_arr)) {
            return 0;
        }

        $pks = array_keys($old_data_arr);

        isset(static::$_updated_time_key)
        && $new_param[static::$_updated_time_key] = time();

        $res = self::db()->where_in(static::$_primary_key, $pks)->update(
            static::$_table,
            $new_param
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }


        self::flushUpdateBatchCache($new_param, $old_data_arr);


        return self::db()->affected_rows();
    }

    public static function deleteByPk($pk)
    {
        static::checkPk($pk);

        if (empty(static::$_idx) && empty(static::$_udx)) {
            $old_data = [static::$_primary_key => $pk];
        } else {
            $old_data = self::fetchByPk($pk);

            if (empty($old_data)) {
                return 0;
            }
        }

        $res = self::db()->delete(
            static::$_table,
            [static::$_primary_key => $pk]
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushDeleteCache($old_data);

        return self::db()->affected_rows();
    }

    public static function deleteByUdx(array $udx)
    {
        self::checkUdx($udx);

        $old_data = self::fetchByUdx($udx);

        if (empty($old_data)) {
            return 0;
        }

        $res = self::db()->delete(
            static::$_table,
            [static::$_primary_key => $old_data[static::$_primary_key]]
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushDeleteCache($old_data);

        return self::db()->affected_rows();
    }

    public static function deleteByIdx(array $idx)
    {
        $tpl = self::checkIdxNotMatchSort($idx);

        $old_pks = self::findPksByIdx($idx, $tpl['sort']);
        if (empty($old_pks['total'])) {
            return 0;
        }

        $pks = $old_pks['data'];
        $old_data_arr = self::findByPks($pks);

        $res = self::db()->where_in(static::$_primary_key, $pks)->delete(
            static::$_table
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushDeleteBatchCache($old_data_arr);

        return self::db()->affected_rows();
    }

    public static function deleteByPks(array $pks)
    {
        if (empty($pks)) {
            return 0;
        }

        array_walk($pks, 'static::checkPk');

        $old_data_arr = self::findByPks($pks);
        if (empty($old_data_arr)) {
            return 0;
        }

        $pks = array_keys($old_data_arr);

        $res = self::db()->where_in(static::$_primary_key, $pks)->delete(
            static::$_table
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushDeleteBatchCache($old_data_arr);

        return self::db()->affected_rows();
    }

    /**
     * @param array  $udx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
     *
     * @return int
     * @throws \Exception
     */
    public static function deleteByUdxLastFieldWhereIn(
        array $udx,
        string $where_in_key,
        array $where_in_value_arr
    ) {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $pks = self::findPksByUdxLastFieldWhereIn(
            $udx,
            $where_in_key,
            $where_in_value_arr
        );

        if (empty($pks)) {
            return 0;
        }

        $old_data_arr = self::findByPks($pks);
        $res = self::db()->where_in(static::$_primary_key, $pks)->delete(
            static::$_table
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushDeleteBatchCache($old_data_arr);

        return self::db()->affected_rows();
    }

    /**
     * @param array  $idx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
     *
     * @return int
     * @throws \Exception
     */
    public static function deleteByIdxLastFieldWhereIn(
        array $idx,
        string $where_in_key,
        array $where_in_value_arr
    ) {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $idx[$where_in_key] = '%s';
        $tpl = self::checkIdxNotMatchSort($idx);
        unset($idx[$where_in_key]);

        $pks = self::findMultiPksByIdxLastFieldWhereIn(
            $idx,
            $where_in_key,
            $where_in_value_arr,
            $tpl['sort']
        );
        $pks = array_merge(...$pks);
        if (empty($pks)) {
            return 0;
        }

        $old_data_arr = self::findByPks($pks);
        $res = self::db()->where_in(static::$_primary_key, $pks)->delete(
            static::$_table
        );
        log_message('debug', self::db()->last_query());

        if ($res === false) {
            self::throwSqlErr();
        }

        self::flushDeleteBatchCache($old_data_arr);

        return self::db()->affected_rows();
    }

    /**
     * @return \CI\libraries\Cache\Drivers\memcached|\CI\libraries\Cache\Drivers\redis|\CI\libraries\Cache\Drivers\file
     */
    final public static function cache()
    {
        return cache(static::$_cache_group);
    }

    final protected static function flushInsertCache(array &$param)
    {
        $del_keys[self::getPkCacheKey($param[static::$_primary_key])] = 1;

        if (!empty(static::$_idx)) {
            foreach (static::$_idx as $v) {
                $del_keys[self::getIdxCacheKey($v, $param)] = 1;
                $del_keys[self::getIdxCountCacheKey($v, $param)] = 1;
            }
        }

        if (!empty(static::$_udx)) {
            if (self::db()->trans_depth()) {
                foreach (static::$_udx as $v) {
                    $del_keys[self::getUdxCacheKey($v, $param)] = 1;
                }
            } else {
                foreach (static::$_udx as $v) {
                    $set_keys[self::getUdxCacheKey($v, $param)]
                        = $param[static::$_primary_key];
                }
                if (isset($set_keys)) {
                    self::cache()->setMultiple($set_keys);
                    log_message(
                        'debug',
                        __METHOD__.' set multi insert cache key: '.var_export(
                            $set_keys,
                            true
                        )
                    );
                }
            }
        }


        self::cache()->deleteMultiple(array_keys($del_keys));
        log_message(
            'debug',
            __METHOD__.' del multi insert cache key: '.var_export(
                $del_keys,
                true
            )
        );


        return true;
    }

    final protected static function flushInsertBatchCache(array &$params)
    {
        $del_keys = [];
        $flush_idx = !empty(static::$_idx);
        $flush_udx = !empty(static::$_udx);
        foreach ($params as &$param) {
            $del_keys[self::getPkCacheKey($param[static::$_primary_key])] = 1;

            if ($flush_idx) {
                foreach (static::$_idx as $v) {
                    $del_keys[self::getIdxCacheKey($v, $param)] = 1;
                    $del_keys[self::getIdxCountCacheKey($v, $param)] = 1;
                }
            }
            if ($flush_udx) {
                if (self::db()->trans_depth()) {
                    foreach (static::$_udx as $v) {
                        $del_keys[self::getUdxCacheKey($v, $param)] = 1;
                    }
                } else {
                    foreach (static::$_udx as $v) {
                        $set_keys[self::getUdxCacheKey($v, $param)]
                            = $param[static::$_primary_key];
                    }
                }
            }
        }
        unset($param);

        if (isset($set_keys)) {
            self::cache()->setMultiple($set_keys);
            log_message(
                'debug',
                __METHOD__.' set multi insert cache key: '.var_export(
                    $set_keys,
                    true
                )
            );
        }


        self::cache()->deleteMultiple(array_keys($del_keys));
        log_message(
            'debug',
            __METHOD__.' del multi insert cache key: '.var_export(
                $del_keys,
                true
            )
        );


        return true;
    }

    final protected static function flushUpdateCache(
        array &$new_param,
        array &$old_data
    ) {
        $del_keys[self::getPkCacheKey($old_data[static::$_primary_key])] = 1;

        if (!empty(static::$_idx)) {
            $merge_param = $new_param + $old_data;
            foreach (static::$_idx as $v) {
                $intersect = array_intersect_key($new_param, $v['where']);
                if (!empty($intersect)) {
                    $del_keys[self::getIdxCacheKey($v, $merge_param)] = 1;
                    $del_keys[self::getIdxCacheKey($v, $old_data)] = 1;
                    $del_keys[self::getIdxCountCacheKey($v, $merge_param)] = 1;
                    $del_keys[self::getIdxCountCacheKey($v, $old_data)] = 1;
                    continue;
                }

                $intersect = array_intersect_key($new_param, $v['sort']);
                if (!empty($intersect)) {
                    $del_keys[self::getIdxCacheKey($v, $old_data)] = 1;
                    continue;
                }
            }
        }

        if (!empty(static::$_udx)) {
            isset($merge_param) || $merge_param = $new_param + $old_data;
            if (self::db()->trans_depth()) {
                foreach (static::$_udx as $v) {
                    $intersect = array_intersect_key($new_param, $v);
                    if (!empty($intersect)) {
                        $del_keys[self::getUdxCacheKey($v, $merge_param)] = 1;
                        $del_keys[self::getUdxCacheKey($v, $old_data)] = 1;
                    }
                }
            } else {
                foreach (static::$_udx as $v) {
                    $intersect = array_intersect_key($new_param, $v);
                    if (!empty($intersect)) {
                        $set_keys[self::getUdxCacheKey($v, $merge_param)]
                            = $old_data[static::$_primary_key];
                        $del_keys[self::getUdxCacheKey($v, $old_data)] = 1;
                    }
                }
                if (isset($set_keys)) {
                    self::cache()->setMultiple($set_keys);
                    log_message(
                        'debug',
                        __METHOD__.' set multi update cache key: '.var_export(
                            $set_keys,
                            true
                        )
                    );
                }
            }
        }


        self::cache()->deleteMultiple(array_keys($del_keys));
        log_message(
            'debug',
            __METHOD__.' del multi update cache key: '.var_export(
                $del_keys,
                true
            )
        );


        return true;
    }

    final protected static function flushUpdateBatchCache(
        array &$new_param,
        array &$old_data_arr
    ) {
        $del_keys = [];
        $flush_idx = !empty(static::$_idx);
        $flush_udx = !empty(static::$_udx);
        foreach ($old_data_arr as &$old_data) {
            $merge_param = $new_param + $old_data;
            $del_keys[self::getPkCacheKey($old_data[static::$_primary_key])]
                = 1;

            if ($flush_idx) {
                foreach (static::$_idx as $v) {
                    $intersect = array_intersect_key($new_param, $v['where']);
                    if (!empty($intersect)) {
                        $del_keys[self::getIdxCacheKey($v, $merge_param)] = 1;
                        $del_keys[self::getIdxCacheKey($v, $old_data)] = 1;
                        $del_keys[self::getIdxCountCacheKey($v, $merge_param)]
                            = 1;
                        $del_keys[self::getIdxCountCacheKey($v, $old_data)] = 1;
                        continue;
                    }

                    $intersect = array_intersect_key($new_param, $v['sort']);
                    if (!empty($intersect)) {
                        $del_keys[self::getIdxCacheKey($v, $old_data)] = 1;
                        continue;
                    }
                }
            }

            if ($flush_udx) {
                if (self::db()->trans_depth()) {
                    foreach (static::$_udx as $v) {
                        $intersect = array_intersect_key($new_param, $v);
                        if (!empty($intersect)) {
                            $del_keys[self::getUdxCacheKey($v, $merge_param)]
                                = 1;
                            $del_keys[self::getUdxCacheKey($v, $old_data)] = 1;
                        }
                    }
                } else {
                    foreach (static::$_udx as $v) {
                        $intersect = array_intersect_key($new_param, $v);
                        if (!empty($intersect)) {
                            $set_keys[self::getUdxCacheKey($v, $merge_param)]
                                = $old_data[static::$_primary_key];
                            $del_keys[self::getUdxCacheKey($v, $old_data)] = 1;
                        }
                    }
                }
            }
        }
        unset($old_data);

        if (isset($set_keys)) {
            self::cache()->setMultiple($set_keys);
            log_message(
                'debug',
                __METHOD__.' set multi update cache key: '.var_export(
                    $set_keys,
                    true
                )
            );
        }


        self::cache()->deleteMultiple(array_keys($del_keys));
        log_message(
            'debug',
            __METHOD__.' del multi update cache key: '.var_export(
                $del_keys,
                true
            )
        );


        return true;
    }

    final protected static function flushDeleteCache(array &$old_data)
    {
        //$set_keys = [];
        $del_keys[self::getPkCacheKey($old_data[static::$_primary_key])] = 1;

        if (!empty(static::$_idx)) {
            foreach (static::$_idx as $v) {
                $del_keys[self::getIdxCacheKey($v, $old_data)] = 1;
                $del_keys[self::getIdxCountCacheKey($v, $old_data)] = 1;
            }
        }

        //if (self::db()->trans_depth()) {
        if (!empty(static::$_udx)) {
            foreach (static::$_udx as $v) {
                $del_keys[self::getUdxCacheKey($v, $old_data)] = 1;
            }
        }
        /*} else {
            foreach (static::$_udx as $v) {
                $set_keys[self::getUdxCacheKey($v, $old_data)] = null;
            }
        }*/

        //if (!empty($del_keys)) {
        self::cache()->deleteMultiple(array_keys($del_keys));
        log_message(
            'debug',
            __METHOD__.' del multi delete cache key: '.var_export(
                $del_keys,
                true
            )
        );

        /*}

        if (!empty($set_keys)) {
            self::cache()->setMultiple($set_keys);
            log_message('debug', __METHOD__ . ' set multi delete cache key: ' . var_export($set_keys, true));
        }*/

        return true;
    }

    final protected static function flushDeleteBatchCache(array &$old_data_arr)
    {
        //$set_keys = [];
        $del_keys = [];
        $flush_idx = !empty(static::$_idx);
        $flush_udx = !empty(static::$_udx);
        foreach ($old_data_arr as &$old_data) {
            $del_keys[self::getPkCacheKey($old_data[static::$_primary_key])]
                = 1;

            if ($flush_idx) {
                foreach (static::$_idx as $v) {
                    $del_keys[self::getIdxCacheKey($v, $old_data)] = 1;
                    $del_keys[self::getIdxCountCacheKey($v, $old_data)] = 1;
                }
            }

            //if (self::db()->trans_depth()) {
            if ($flush_udx) {
                foreach (static::$_udx as $v) {
                    $del_keys[self::getUdxCacheKey($v, $old_data)] = 1;
                }
            }
            /*} else {
                foreach (static::$_udx as $v) {
                    $set_keys[self::getUdxCacheKey($v, $old_data)] = null;
                }
            }*/
        }
        unset($old_data);
        //if (!empty($del_keys)) {
        self::cache()->deleteMultiple(array_keys($del_keys));
        log_message(
            'debug',
            __METHOD__.' del multi delete cache key: '.var_export(
                $del_keys,
                true
            )
        );

        /*}

        if (!empty($set_keys)) {
            self::cache()->setMultiple($set_keys);
            log_message('debug', __METHOD__ . ' set multi delete cache key: ' . var_export($set_keys, true));
        }*/

        return true;
    }
}
