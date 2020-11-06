<?php

namespace CI\core\Dao;

use CI\core\Exceptions\SeniorException;
use Psr\Log\LogLevel;

/**
 * Class Dao
 * 所有Dao最顶级父类
 *
 * @package CI\core\dao
 */
abstract class Dao implements IDao
{
    protected static $_table;//数据库表名，格式：库名.表名，mongodb为库名，集合名
    protected static $_active_group = '';//使用的数据库配置，见 config\环境\database.php
    protected static $_insert_fields = [];//可插入的字段，元素格式：field_name => '默认值'
    protected static $_update_fields = [];//可更新的字段，元素格式：field_name => ''
    protected static $_select_fields = [];//可查询的字段，元素格式：field_name，里面要写主键，不然刷新缓存会报错
    protected static $_udx = [];//唯一索引，元素格式：['parent_id' => '', 'name' => '']
    protected static $_idx = [];//普通索引，元素格式：['where' => ['cate_id' => ''], 'sort' => ['id'=>'asc'], 'limit' => 200 ]，在mysql_Dao_no_cache中也可以做>,<等查询，limit只在mysql_Dao_cache中生效表示此key只缓存前xx条记录
    protected static $_primary_key = '';//主键
    protected static $_pk_type = 'int';//主键类型,string|int
    protected static $_created_time_key = null;//插入时间字段key
    protected static $_updated_time_key = null;//修改时间字段key
    //protected static $_foreign_key = '';//外键
    protected static $_auto_pk = true;//在mysql中控制是否为自增id，在mongodb中为自动生成的_id

    /*public static function active_group($active_group)
    {
        static::$_active_group = $active_group;
    }*/

    final protected static function checkIdxMatchSort(array $idx, array $sort = [])
    {
        $c = count($idx);
        $d = count($sort);
        foreach (static::$_idx as $v) {
            if ($c == count($v['where']) &&
                ($c == 0 || empty(array_diff_key($idx, $v['where']))) &&
                $d == count($v['sort']) &&
                ($d == 0 || $sort === $v['sort'])
            ) {
                return $v;
            }
        }

        throw new SeniorException(
            sprintf(
                'table:%s %s, idx:%s and sort:%s must be defined in $_idx property',
                static::$_table,
                self::getCalledMethod(3),
                json_encode($idx),
                json_encode($sort)
            ),
            err('param'),
            LogLevel::CRITICAL
        );
    }

    final protected static function checkIdxNotMatchSort(array $idx)
    {
        $c = count($idx);
        $res = null;
        $t = 0;
        foreach (static::$_idx as $v) {
            if ($c == count($v['where']) &&
                ($c == 0 || empty(array_diff_key($idx, $v['where'])))
            ) {
                if (empty($v['limit'])) {
                    return $v;
                }
                if ($v['limit'] > $t) {
                    $res = $v;
                    $t = $v['limit'];
                }
            }
        }
        if (isset($res)) {
            return $res;
        }

        throw new SeniorException(
            sprintf('table:%s %s, idx:%s must be defined in $_idx property', static::$_table, self::getCalledMethod(3), json_encode($idx)),
            err('param'),
            LogLevel::CRITICAL
        );
    }

    final protected static function checkUdx(array $udx)
    {
        $c = count($udx);
        foreach (static::$_udx as $v) {
            if ($c == count($v) && empty(array_diff_key($v, $udx))) {
                return $v;
            }
        }

        throw new SeniorException(
            sprintf('table:%s %s, udx:%s must be defined in $_udx property', static::$_table, self::getCalledMethod(3), json_encode($udx)),
            err('param'),
            LogLevel::CRITICAL
        );
    }

    protected static function checkPk(&$pk)
    {
        if (static::$_auto_pk) {
            static::$_pk_type = 'int';
        }

        switch (static::$_pk_type) {
            case 'string':
                if (!is_string($pk)) {
                    throw new SeniorException(
                        sprintf('table:%s %s, pk must be string', static::$_table, self::getCalledMethod(3)),
                        err('param'),
                        LogLevel::CRITICAL
                    );
                }
                break;
            case 'integer':
            case 'int':
                if (filter_var($pk, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    throw new SeniorException(
                        sprintf('table:%s %s, pk must be natural number', static::$_table, self::getCalledMethod(3)),
                        err('param'),
                        LogLevel::CRITICAL
                    );
                }
                break;
            default:
                throw new SeniorException(
                    sprintf(
                        'table:%s %s, the primary_key type is not declared',
                        static::$_table,
                        self::getCalledMethod(3)
                    ),
                    err('param'),
                    LogLevel::CRITICAL
                );
        }
    }

    final protected static function checkInsertPk(array &$param)
    {
        if (static::$_auto_pk) {
            if (isset($param[static::$_primary_key])) {
                unset($param[static::$_primary_key]);
            }
            return true;
            /*if (!isset($param[static::$_primary_key])) {
                return true;
            }

            throw new SeniorException(
                sprintf(
                    'table:%s %s, the insert data can\'t has a primary key',
                    static::$_table,
                    self::getCalledMethod(3)
                ),
                err('param'),
                LogLevel::CRITICAL

            );*/
        } else {
            if (isset($param[static::$_primary_key])) {
                static::checkPk($param[static::$_primary_key]);
                return true;
            }

            throw new SeniorException(
                sprintf(
                    'table:%s %s, the insert data need a primary key',
                    static::$_table,
                    self::getCalledMethod(3)
                ),
                err('param'),
                LogLevel::CRITICAL
            );
        }
    }

    final protected static function getCalledMethod($deep)
    {
        $debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $deep--);
        $method = '';
        if (isset($debug[$deep])) {
            $debug = $debug[$deep];
            $method = isset($debug['class']) ? $debug['class'] . $debug['type'] . $debug['function'] : $debug['function'];
        }
        return $method;
    }

    final protected static function throwSqlErr()
    {
        throw new SeniorException(
            sprintf(
                'TABLE:%s %s, ERROR:%s SQL:%s',
                static::$_table,
                self::getCalledMethod(3),
                self::dbError(),
                self::db()->last_query()
            ),
            err('db'),
            LogLevel::CRITICAL
        );
    }

    final protected static function dbError()
    {
        $error = self::db()->error();

        return 'code:' . $error['code'] . ', message:' . $error['message'];
    }

    /**
     * @return \CI_DB_query_builder|\CI_DB_driver|\CI_DB_mysqli_driver|\CI_DB
     */
    final protected static function db()
    {
        return db(static::$_active_group);
    }

    final public static function getNewOne()
    {
        $res = static::$_insert_fields;
        unset($res[static::$_primary_key]);
        return $res;
    }

}
