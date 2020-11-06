<?php

namespace CI\core\Dao;

use MongoDB\BSON\ObjectID;
use MongoDB\Operation\FindOneAndUpdate;
use CI\core\Exceptions\SeniorException;
use Psr\Log\LogLevel;

/**
 * Class mongo_Dao
 * mongodb的dao父类
 *
 * @package CI\core\dao
 */
class MongoDao extends Dao
{
    protected static $_primary_key = '_id';//主键，此项强制为'_id'，不能修改
    protected static $_pk_type = 'MongoDB\BSON\ObjectID';//_id的数据类型

    protected static function checkPk(&$pk)
    {
        self::encodePk($pk);
    }

    /**
     * 格式化主键
     *
     * @param &$pk
     *
     * @throws \Exception
     */
    final protected static function encodePk(&$pk)
    {
        if (static::$_auto_pk) {
            $pk = new ObjectID($pk);
        } else {
            switch (static::$_pk_type) {
                case 'string':
                    $pk = (string)$pk;
                    break;
                case 'integer':
                case 'int':
                    $pk = intval($pk);
                    if (filter_var($pk, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        throw new SeniorException(
                            sprintf('table:%s %s, pk must be natural number', static::$_table,
                                self::getCalledMethod(3)),
                            err('param'),
                            LogLevel::CRITICAL
                        );
                    }
                    break;
                case 'MongoDB\BSON\ObjectID':
                    $pk = new ObjectID($pk);
                    break;
                default:
                    throw new SeniorException(
                        sprintf(
                            'table:%s %s, the _id data type is not declared',
                            static::$_table,
                            self::getCalledMethod(3)
                        ),
                        err('param'),
                        LogLevel::CRITICAL
                    );
            }
        }
    }

    /**
     * 解码主键
     *
     * @param $pk
     */
    final protected static function decodePk(&$pk)
    {
        static::$_pk_type == 'MongoDB\BSON\ObjectID' && $pk = (string)$pk;
    }

    public static function fetchPkByUdx(array $udx, array $selected = [])
    {
        self::checkUdx($udx);
        $options['projection'] = ['_id' => 1];

        $document = self::collection()->findOne($udx, $options);
        if (!empty($document)) {
            self::decodePk($document['_id']);
            return $document['_id'];
        } else {
            return null;
        }
    }

    public static function fetchByUdx(array $udx, array $selected = [])
    {
        self::checkUdx($udx);
        $options = [];
        if (empty($selected)) {
            $selected = static::$_select_fields;
        }
        $options['projection'] = array_fill_keys($selected, 1);

        $document = self::collection()->findOne($udx, $options);
        if (!empty($document)) {
            self::decodePk($document['_id']);
        }

        return $document;
    }

    /**
     * @param array  $udx
     * @param string $where_in_key
     * @param array  $where_in_value_arr
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
        $udx[$where_in_key] = ['$in' => $where_in_value_arr];

        $options['projection'] = [static::$_primary_key => 1, $where_in_key => 1];
        $cursor = self::collection()->find($udx, $options);

        $documents = [];

        $temp = $cursor->toArray();

        if (count($temp)) {
            $pks = array_column($temp, '_id');
            $fields = explode('.', $where_in_key);
            foreach ($fields as $field) {
                $temp = array_column($temp, $field);
            }
            $temp = array_combine($temp, $pks);
            //调整结果顺序，对应传入ids的顺序
            foreach ($where_in_value_arr as $v) {
                if (isset($temp[$v])) {
                    self::decodePk($temp[$v]);
                    $documents[$v] = $temp[$v];
                }
            }
        }

        return $documents;
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
        if (empty($where_in_value_arr)) {
            return [];
        }

        $udx[$where_in_key] = '%s';
        self::checkUdx($udx);
        $udx[$where_in_key] = ['$in' => $where_in_value_arr];


        if (empty($selected)) {
            $selected = static::$_select_fields;
        }

        if (!self::fieldInArray($where_in_key, $selected)) {
            $selected[] = $where_in_key;
        }

        $options['projection'] = array_fill_keys($selected, 1);

        $cursor = self::collection()->find($udx, $options);

        $documents = [];

        $temp = $cursor->toArray();

        if (count($temp)) {
            $t = $temp;
            $fields = explode('.', $where_in_key);
            foreach ($fields as $field) {
                $temp = array_column($temp, $field);
            }
            $temp = array_combine($temp, $t);
            //调整结果顺序，对应传入ids的顺序
            foreach ($where_in_value_arr as $v) {
                if (isset($temp[$v])) {
                    self::decodePk($temp[$v]['_id']);
                    $documents[$v] = $temp[$v];
                }
            }
        }

        return $documents;
    }

    public static function fetchByPk($pk, array $selected = [])
    {
        static::checkPk($pk);
        $options = [];
        if (empty($selected)) {
            $selected = static::$_select_fields;
        }
        $options['projection'] = array_fill_keys($selected, 1);

        $document = self::collection()->findOne(['_id' => $pk], $options);
        if (!empty($document)) {
            self::decodePk($document['_id']);
        }

        return $document;
    }

    /**
     * @param array $pks
     * @param array $selected
     *
     * @return array [ id1 => value1, id2 => value2]
     */
    public static function findByPks(array $pks, array $selected = [])
    {
        if (empty($pks)) {
            return [];
        }
        array_walk($pks, 'static::checkPk');
        $options = [];
        if (empty($selected)) {
            $selected = static::$_select_fields;
        }
        $options['projection'] = array_fill_keys($selected, 1);


        $cursor = self::collection()->find(['_id' => ['$in' => $pks]], $options);
        $documents = [];
        $temp = $cursor->toArray();
        if (count($temp)) {
            $temp = array_column($temp, null, '_id');
            //调整结果顺序，对应传入ids的顺序
            foreach ($pks as $v) {
                self::decodePk($v);
                if (isset($temp[$v])) {
                    self::decodePk($temp[$v]['_id']);
                    $documents[$v] = $temp[$v];
                }
            }
        }
        return $documents;
    }

    public static function countByIdx(array $idx = [])
    {
        return self::collection()->count($idx);
    }

    public static function countMultiByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr)
    {
        if (empty($where_in_value_arr)) {
            return [];
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxNotMatchSort($idx);
        $idx[$where_in_key] = ['$in' => $where_in_value_arr];

        $cursor = self::collection()->aggregate([
            ['$match' => $idx],
            ['$group' => ['_id' => '$' . $where_in_key, 'count' => ['$sum' => 1]]],
        ]);

        $t = array_column($cursor->toArray(), 'count', '_id');

        $res = [];
        foreach ($where_in_value_arr as $v) {
            $res[$v] = intval($t[$v] ?? 0);
        }

        return $res;
    }

    /**
     * @param array $idx
     * @param array $sort
     * @param array $options ['per_page'=>10, 'page'=>1, 'selected' => []]
     *
     * @return array array [ total => 2, data => [ id1 => [], id2 => []]]
     * @throws \Exception
     */
    public static function findByIdx(array $idx = [], array $sort = [], array $options = [])
    {
        $res = [
            'total' => 0,
            'data' => [],
        ];

        self::checkIdxMatchSort($idx, $sort);
        $res['idx'] = $idx;
        $res['sort'] = $sort;


        $new_options = [];
        if (!empty($options['per_page'])) {
            $res['total'] = self::collection()->count($idx);
            $res['per_page'] = $new_options['limit'] = max(1, intval($options['per_page']));

            if (empty($options['page'])) {
                $res['current_page'] = 1;
            } else {
                $res['current_page'] = max(1, intval($options['page']));
            }

            $new_options['skip'] = ($res['current_page'] - 1) * $res['per_page'];
            if ($new_options['skip'] >= $res['total']) {
                return $res;
            }
        }


        if (empty($options['selected'])) {
            $options['selected'] = static::$_select_fields;
        }
        $new_options['projection'] = array_fill_keys($options['selected'], 1);


        if (!empty($sort)) {
            foreach ($sort as $k => $v) {
                $new_options['sort'][$k] = $v == 'desc' ? -1 : 1;
            }
        }

        $cursor = self::collection()->find($idx, $new_options);

        $res['data'] = $cursor->toArray();
        if (count($res['data'])) {
            foreach ($res['data'] as $k => &$v) {
                self::decodePk($v['_id']);
            }
            unset($v);
        }

        empty($res['per_page']) && $res['total'] = count($res['data']);
        $res['data'] = array_column($res['data'], null, static::$_primary_key);

        return $res;
    }

    /**
     * @param array $idx
     * @param array $sort
     * @param array $options
     *
     * @return array array [ total => 2, data => [ id1, id2 ]]
     * @throws \Exception
     */
    public static function findPksByIdx(array $idx = [], array $sort = [], array $options = [])
    {
        $res = [
            'total' => 0,
            'data' => [],
        ];

        self::checkIdxMatchSort($idx, $sort);
        $res['idx'] = $idx;
        $res['sort'] = $sort;


        $new_options = [];
        if (!empty($options['per_page'])) {
            $res['total'] = self::collection()->count($idx);
            $res['per_page'] = $new_options['limit'] = max(1, intval($options['per_page']));

            if (empty($options['page'])) {
                $res['current_page'] = 1;
            } else {
                $res['current_page'] = max(1, intval($options['page']));
            }

            $new_options['skip'] = ($res['current_page'] - 1) * $res['per_page'];
            if ($new_options['skip'] >= $res['total']) {
                return $res;
            }
        }

        $new_options['projection'] = [static::$_primary_key => 1];

        if (!empty($sort)) {
            foreach ($sort as $k => $v) {
                $new_options['sort'][$k] = $v == 'desc' ? -1 : 1;
            }
        }

        $cursor = self::collection()->find($idx, $new_options);

        $pks = $cursor->toArray();
        if (count($pks)) {
            foreach ($pks as $k => &$v) {
                self::decodePk($v['_id']);
            }
            unset($v);
            $res['data'] = array_column($pks, static::$_primary_key);
        }
        empty($res['per_page']) && $res['total'] = count($pks);
        return $res;
    }

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
        $idx[$where_in_key] = ['$in' => $where_in_value_arr];

        $options['projection'] = [static::$_primary_key => 1, $where_in_key => 1];

        if (!empty($sort)) {
            foreach ($sort as $k => $v) {
                $options['sort'][$k] = $v == 'desc' ? -1 : 1;
            }
        }

        $cursor = self::collection()->find($idx, $options);

        $temp = $cursor->toArray();
        $res = [];
        if (count($temp) <= 0) {
            foreach ($where_in_value_arr as $v) {
                $res[$v] = [];
            }
        } else {
            $pks = array_column($temp, '_id');

            $fields = explode('.', $where_in_key);
            foreach ($fields as $field) {
                $temp = array_column($temp, $field);
            }

            $t = [];
            foreach ($temp as $k => $v) {
                self::decodePk($pks[$k]);
                $t[$v][] = $pks[$k];
            }

            foreach ($where_in_value_arr as $v) {
                $res[$v] = $t[$v] ?? [];
            }
        }

        return $res;
    }

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
        $idx[$where_in_key] = ['$in' => $where_in_value_arr];

        if (empty($selected)) {
            $selected = static::$_select_fields;
        }

        if (!self::fieldInArray($where_in_key, $selected)) {
            $selected[] = $where_in_key;
        }

        $options['projection'] = array_fill_keys($selected, 1);

        if (!empty($sort)) {
            foreach ($sort as $k => $v) {
                $options['sort'][$k] = $v == 'desc' ? -1 : 1;
            }
        }

        $cursor = self::collection()->find($idx, $options);

        $temp = $cursor->toArray();
        $res = [];
        if (count($temp) <= 0) {
            foreach ($where_in_value_arr as $v) {
                $res[$v] = [];
            }
        } else {
            $data = $temp;
            $fields = explode('.', $where_in_key);
            foreach ($fields as $field) {
                $temp = array_column($temp, $field);
            }

            $t = [];
            foreach ($temp as $k => $v) {
                self::decodePk($data[$k]['_id']);
                $t[$v][] = $data[$k];
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

        $new_param = array_intersect_key($param, static::$_insert_fields);

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();
        isset(static::$_created_time_key) && $new_param[static::$_created_time_key] = time();

        $result = self::collection()->insertOne($new_param);

        $insert_id = $result->getInsertedId();
        self::decodePk($insert_id);
        return $insert_id;
    }

    public static function insertBatch(array $params, $ordered = true)
    {
        if (empty($params)) {
            return 0;
        }

        foreach ($params as $k => &$v) {
            self::checkInsertPk($v);
            $v = array_intersect_key($v, static::$_insert_fields);
            isset(static::$_updated_time_key) && $v[static::$_updated_time_key] = time();
            isset(static::$_created_time_key) && $v[static::$_created_time_key] = time();
        }
        unset($v);
        $result = self::collection()->insertMany($params, ['ordered' => $ordered]);

        return $result->getInsertedCount();
    }

    public static function updateByPk(array $param, $pk, $upsert = false)
    {
        static::checkPk($pk);

        if (empty($param)) {
            return 0;
        }

        foreach ($param as $key => &$value) {
            if (!empty($value) && is_array($value)) {
                $value = self::fieldArrayIntersectKey($value, static::$_update_fields);
                if (!empty($value)) {
                    $new_param[$key] = $value;
                }
            }
        }
        unset($value);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param['$set'][static::$_updated_time_key] = time();

        /*if (isset($new_param['$push'], $new_param['$pull'])) {
            if (!empty(array_intersect_key($new_param['$push'], $new_param['$pull']))) {
                self::collection()->updateOne(['_id' => $pk], ['$pull' => $new_param['$pull']], ['upsert' => $upsert]);
                unset($new_param['$pull']);
            }
        }*/

        $res = self::collection()->updateOne(['_id' => $pk], $new_param, ['upsert' => $upsert]);

        return $res->getModifiedCount();
    }

    public static function updateByUdx(array $param, array $udx, $upsert = false)
    {
        self::checkUdx($udx);

        if (empty($param)) {
            return 0;
        }

        foreach ($param as $key => &$value) {
            if (!empty($value) && is_array($value)) {
                $value = self::fieldArrayIntersectKey($value, static::$_update_fields);
                if (!empty($value)) {
                    $new_param[$key] = $value;
                }
            }
        }
        unset($value);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param['$set'][static::$_updated_time_key] = time();

        /*if (isset($new_param['$push'], $new_param['$pull'])) {
            if (!empty(array_intersect_key($new_param['$push'], $new_param['$pull']))) {
                self::collection()->updateOne($udx, ['$pull' => $new_param['$pull']], ['upsert' => $upsert]);
                unset($new_param['$pull']);
            }
        }*/

        $res = self::collection()->updateOne($udx, $new_param, ['upsert' => $upsert]);

        return $res->getModifiedCount();
    }

    public static function updateByUdxLastFieldWhereIn(
        array $param,
        array $udx,
        string $where_in_key,
        array $where_in_value_arr,
        $upsert = false
    ) {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $udx[$where_in_key] = '%s';
        self::checkUdx($udx);
        $udx[$where_in_key] = ['$in' => $where_in_value_arr];

        if (empty($param)) {
            return 0;
        }

        foreach ($param as $key => &$value) {
            if (!empty($value) && is_array($value)) {
                $value = self::fieldArrayIntersectKey($value, static::$_update_fields);
                if (!empty($value)) {
                    $new_param[$key] = $value;
                }
            }
        }
        unset($value);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param['$set'][static::$_updated_time_key] = time();

        /*if (isset($new_param['$push'], $new_param['$pull'])) {
            if (!empty(array_intersect_key($new_param['$push'], $new_param['$pull']))) {
                self::collection()->updateOne($udx, ['$pull' => $new_param['$pull']], ['upsert' => $upsert]);
                unset($new_param['$pull']);
            }
        }*/

        $res = self::collection()->updateOne($udx, $new_param, ['upsert' => $upsert]);

        return $res->getModifiedCount();
    }

    public static function updateByIdx(array $param, array $idx, $upsert = false)
    {
        self::checkIdxNotMatchSort($idx);

        if (empty($param)) {
            return 0;
        }

        foreach ($param as $key => &$value) {
            if (!empty($value) && is_array($value)) {
                $value = self::fieldArrayIntersectKey($value, static::$_update_fields);
                if (!empty($value)) {
                    $new_param[$key] = $value;
                }
            }
        }
        unset($value);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param['$set'][static::$_updated_time_key] = time();

        if (isset($new_param['$push'], $new_param['$pull'])) {
            if (!empty(array_intersect_key($new_param['$push'], $new_param['$pull']))) {
                self::collection()->updateMany($idx, ['$push' => $new_param['$push']], ['upsert' => $upsert]);
                unset($new_param['$push']);
            }
        }

        $res = self::collection()->updateMany($idx, $new_param, ['upsert' => $upsert]);

        return $res->getModifiedCount();
    }

    public static function updateByIdxLastFieldWhereIn(
        array $param,
        array $idx,
        string $where_in_key,
        array $where_in_value_arr,
        $upsert = false
    ) {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxNotMatchSort($idx);
        $idx[$where_in_key] = ['$in' => $where_in_value_arr];

        if (empty($param)) {
            return 0;
        }

        foreach ($param as $key => &$value) {
            if (!empty($value) && is_array($value)) {
                $value = self::fieldArrayIntersectKey($value, static::$_update_fields);
                if (!empty($value)) {
                    $new_param[$key] = $value;
                }
            }
        }
        unset($value);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param['$set'][static::$_updated_time_key] = time();

        /*if (isset($new_param['$push'], $new_param['$pull'])) {
            if (!empty(array_intersect_key($new_param['$push'], $new_param['$pull']))) {
                self::collection()->updateOne($idx, ['$pull' => $new_param['$pull']], ['upsert' => $upsert]);
                unset($new_param['$pull']);
            }
        }*/

        $res = self::collection()->updateOne($idx, $new_param, ['upsert' => $upsert]);

        return $res->getModifiedCount();
    }

    public static function updateByPks(array $param, array $pks, $upsert = false)
    {
        if (empty($pks) || empty($param)) {
            return 0;
        }

        array_walk($pks, 'static::checkPk');

        foreach ($param as $key => &$value) {
            if (!empty($value) && is_array($value)) {
                $value = self::fieldArrayIntersectKey($value, static::$_update_fields);
                if (!empty($value)) {
                    $new_param[$key] = $value;
                }
            }
        }
        unset($value);

        if (empty($new_param)) {
            return 0;
        }

        isset(static::$_updated_time_key) && $new_param['$set'][static::$_updated_time_key] = time();

        if (isset($new_param['$push'], $new_param['$pull'])) {
            if (!empty(array_intersect_key($new_param['$push'], $new_param['$pull']))) {
                self::collection()->updateMany(['_id' => ['$in' => $pks]], ['$push' => $new_param['$push']],
                    ['upsert' => $upsert]);
                unset($new_param['$push']);
            }
        }

        $res = self::collection()->updateMany(['_id' => ['$in' => $pks]], $new_param, ['upsert' => $upsert]);

        return $res->getModifiedCount();
    }

    public static function replaceByPk(array $param, $pk, $upsert = false)
    {
        static::checkPk($pk);

        $new_param = array_intersect_key($param, static::$_insert_fields);

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();
        //isset(static::$_created_time_key) && $new_param[static::$_created_time_key] = time();

        $res = self::collection()->replaceOne(['_id' => $pk], $new_param, ['upsert' => $upsert]);

        return $res->getModifiedCount();
    }

    public static function deleteByPk($pk)
    {
        static::checkPk($pk);

        $res = self::collection()->deleteOne(['_id' => $pk]);

        return $res->getDeletedCount();
    }

    public static function deleteByUdx(array $udx)
    {
        self::checkUdx($udx);

        $res = self::collection()->deleteOne($udx);

        return $res->getDeletedCount();
    }

    public static function deleteByIdx(array $idx)
    {
        self::checkIdxNotMatchSort($idx);

        $res = self::collection()->deleteMany($idx);

        return $res->getDeletedCount();
    }

    public static function deleteByPks(array $pks)
    {
        if (empty($pks)) {
            return 0;
        }

        array_walk($pks, 'static::checkPk');

        $res = self::collection()->deleteMany(['_id' => ['$in' => $pks]]);

        return $res->getDeletedCount();
    }

    public static function deleteByUdxLastFieldWhereIn(array $udx, string $where_in_key, array $where_in_value_arr)
    {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $udx[$where_in_key] = '%s';
        self::checkUdx($udx);
        $udx[$where_in_key] = ['$in' => $where_in_value_arr];

        $res = self::collection()->deleteMany($udx);

        return $res->getDeletedCount();
    }

    public static function deleteByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr)
    {
        if (empty($where_in_value_arr)) {
            return 0;
        }

        $idx[$where_in_key] = '%s';
        self::checkIdxNotMatchSort($idx);
        $idx[$where_in_key] = ['$in' => $where_in_value_arr];


        $res = self::collection()->deleteMany($idx);

        return $res->getDeletedCount();
    }

    public static function fetchAndUpdateByPk(array $param, $pk, array $options = [])
    {
        static::checkPk($pk);
        $new_param = [];

        foreach ($param as $key => &$value) {
            if (!empty($value) && is_array($value)) {
                $value = self::fieldArrayIntersectKey($value, static::$_update_fields);
                if (!empty($value)) {
                    $new_param[$key] = $value;
                }
            }
        }
        unset($value);

        $new_options = [];
        empty($options['return_updated']) || $new_options['returnDocument'] = FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
        empty($options['upsert']) || $new_options['upsert'] = true;
        if (empty($options['selected'])) {
            $options['selected'] = static::$_select_fields;
        }
        $new_options['projection'] = array_fill_keys($options['selected'], 1);

        isset(static::$_updated_time_key) && $new_param['$set'][static::$_updated_time_key] = time();

        /*if (isset($new_param['$push'], $new_param['$pull'])) {
            if (!empty(array_intersect_key($new_param['$push'], $new_param['$pull']))) {
                if (empty($options['return_updated'])) {
                    $document = self::collection()->findOneAndUpdate(['_id' => $pk], ['$pull' => $new_param['$pull']], $new_options);
                    unset($new_param['$pull']);
                    self::collection()->updateOne(['_id' => $pk], $new_param);

                    if (!empty($document)) {
                        self::decodePk($document['_id']);
                    }
                    return $document;
                } else {
                    self::collection()->updateOne(['_id' => $pk], ['$pull' => $new_param['$pull']], ['upsert' => !empty($options['upsert'])]);
                    unset($new_param['$pull']);
                }
            }
        }*/

        $document = self::collection()->findOneAndUpdate(['_id' => $pk], $new_param, $new_options);

        if (!empty($document)) {
            self::decodePk($document['_id']);
        }
        return $document;
    }

    public static function fetchAndReplaceByPk(array $param, $pk, array $options = [])
    {
        static::checkPk($pk);
        $new_param = array_intersect_key($param, static::$_insert_fields);
        $new_options = [];
        empty($options['return_updated']) || $new_options['returnDocument'] = FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
        empty($options['upsert']) || $new_options['upsert'] = true;
        if (empty($options['selected'])) {
            $options['selected'] = static::$_select_fields;
        }
        $new_options['projection'] = array_fill_keys($options['selected'], 1);

        isset(static::$_updated_time_key) && $new_param[static::$_updated_time_key] = time();
        //isset(static::$_created_time_key) && $new_param[static::$_created_time_key] = time();

        $document = self::collection()->findOneAndReplace(['_id' => $pk], $new_param, $new_options);
        if (!empty($document)) {
            self::decodePk($document['_id']);
        }

        return $document;
    }

    /**
     * fetch_and_replace_by_id 更新
     *
     * @param int   $pk
     * @param array $options
     *
     * @return array
     * @throws \Exception
     */
    public static function fetchAndDeleteByPk($pk, array $options = [])
    {
        static::checkPk($pk);
        $new_options = [];
        //empty($options['return_updated']) || $new_options['returnDocument'] = FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
        if (empty($options['selected'])) {
            $options['selected'] = static::$_select_fields;
        }
        $new_options['projection'] = array_fill_keys($options['selected'], 1);

        $document = self::collection()->findOneAndDelete(['_id' => $pk], $new_options);
        if (!empty($document)) {
            self::decodePk($document['_id']);
        }

        return $document;
    }

    /**
     * @return \MongoDB\Collection
     */
    final protected static function collection()
    {
        list($database, $collection) = explode('.', static::$_table, 2);
        $db = self::client();

        if (!isset($db->collections[static::$_table])) {
            $db->collections[static::$_table] = $db->selectCollection($database, $collection);
        }

        return $db->collections[static::$_table];
    }

    /**
     * @return \MongoDB\Database
     */
    final protected static function database()
    {
        $database = strstr(static::$_table, '.', true);
        $db = self::client();

        if (!isset($db->databases[$database])) {
            $db->databases[$database] = $db->selectDatabase($database);
        }

        return $db->databases[$database];
    }

    /**
     * @return \CI\libraries\DB\Drivers\Mongo
     */
    final protected static function client()
    {
        return db(static::$_active_group);
    }

    final protected static function fieldInArray(string $str, array &$arr)
    {
        $s_len = strlen($str);
        $flag = false;
        foreach ($arr as &$value) {
            $v_len = strlen($value);
            if (strncmp($str, $value, $len = min($s_len, $v_len)) == 0) {
                if ($len < $s_len && $str{$len} === '.') {
                    return true;
                } elseif ($len < $v_len && $value{$len} === '.') {
                    $value = $str;
                    $flag = true;
                } elseif ($v_len == $s_len) {
                    return true;
                }
            }
        }
        return $flag;
    }

    final protected static function fieldArrayIntersectKey(array $arr1, array $arr2)
    {
        foreach ($arr1 as $key => $value) {
            if (($n = strpos($key, '.')) !== false) {
                $k = substr($key, 0, $n);
            } else {
                $k = $key;
            }
            if (!isset($arr2[$k])) {
                unset($arr1[$key]);
            }
        }
        return $arr1;
    }

    final protected static function fieldArrayDiffKey(array $arr1, array $arr2)
    {
        foreach ($arr1 as $key => $value) {
            if (($n = strpos($key, '.')) !== false) {
                $k = substr($key, 0, $n);
            } else {
                $k = $key;
            }
            if (isset($arr2[$k])) {
                unset($arr1[$key]);
            }
        }
        return $arr1;
    }

    final protected static function fieldArrayIntersect(array $arr1, array $arr2)
    {
        foreach ($arr1 as $key => $value) {
            if (($n = strpos($value, '.')) !== false) {
                $value = substr($value, 0, $n);
            }
            if (!in_array($value, $arr2)) {
                unset($arr1[$key]);
            }
        }
        return array_values($arr1);
    }
}
