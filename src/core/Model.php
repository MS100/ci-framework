<?php

namespace CI\core;

/**
 * Model Class
 *
 * @package        CodeIgniter
 * @subpackage     Libraries
 * @category       Libraries
 * @author         EllisLab Dev Team
 * @link           https://codeigniter.com/user_guide/libraries/config.html
 */
abstract class Model
{
    /**开启事务
     *
     * @param string $active_group 要开启事务的数据库配置名，如toc、career
     *
     * @return mixed
     */
    public static function transBegin($active_group)
    {
        return db($active_group)->trans_begin();
    }

    /**事务提交
     *
     * @param string $active_group 要提交事务的数据库，如toc、career
     *
     * @return mixed
     */
    public static function transCommit($active_group)
    {
        return db($active_group)->trans_complete();
    }

    /**事务回滚
     *
     * @param string $active_group 要回滚事务的数据库，如toc、career
     *
     * @return mixed
     */
    public static function transRollback($active_group)
    {
        return db($active_group)->trans_rollback();
    }
}
