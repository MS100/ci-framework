<?php

namespace CI\core\Dao;

interface IDao
{
    public static function fetchPkByUdx(array $udx);

    public static function fetchByUdx(array $udx, array $selected = []);

    public static function findPksByUdxLastFieldWhereIn(array $udx, string $where_in_key, array $where_in_value_arr);

    public static function findByUdxLastFieldWhereIn(array $udx, string $where_in_key, array $where_in_value_arr, array $selected = []);

    public static function fetchByPk($pk, array $selected = []);

    public static function findByPks(array $pks, array $selected = []);

    public static function countByIdx(array $idx = []);

    public static function countMultiByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr);

    public static function findByIdx(array $idx = [], array $sort = [], array $options = []);

    public static function findPksByIdx(array $idx = [], array $sort = [], array $options = []);

    public static function findMultiPksByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr, array $sort = []);

    public static function findMultiByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr, array $sort = [], array $selected = []);

    public static function insert(array $param);

    public static function insertBatch(array $params);

    public static function updateByPk(array $param, $pk);

    public static function updateByUdx(array $param, array $udx);

    public static function updateByUdxLastFieldWhereIn(array $param, array $udx, string $where_in_key, array $where_in_value_arr);

    public static function updateByIdx(array $param, array $idx);

    public static function updateByIdxLastFieldWhereIn(array $param, array $idx, string $where_in_key, array $where_in_value_arr);

    public static function updateByPks(array $param, array $pks);

    public static function deleteByPk($pk);

    public static function deleteByUdx(array $udx);

    public static function deleteByIdx(array $idx);

    public static function deleteByPks(array $pks);

    public static function deleteByUdxLastFieldWhereIn(array $udx, string $where_in_key, array $where_in_value_arr);

    public static function deleteByIdxLastFieldWhereIn(array $idx, string $where_in_key, array $where_in_value_arr);

}