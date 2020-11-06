<?php

namespace CI\helpers;

class FileHelper
{
    /**
     * 获取目录下所有的特定后缀的文件名，按层级返回多维数组结构
     *
     * @param string $dir
     * @param string $ext
     *
     * @return array
     */
    public static function getFileRecursiveInDir(
        string $dir,
        string $ext = null
    ) {
        if (!is_dir($dir)) {
            return [];
        }

        $not_ext = !isset($ext);
        $trim_ext = isset($ext) && is_string($ext) && $ext != '' ? '.'.$ext
            : '';
        $file = [];

        $d = dir($dir);
        while (false !== ($entry = $d->read())) {
            if ($entry != '.' && $entry != '..') {
                if (is_dir($dir.'/'.$entry)) {
                    $temp = self::getFileRecursiveInDir($dir.'/'.$entry, $ext);
                    empty($temp) || $file[$entry] = $temp;
                } elseif ($not_ext
                    || pathinfo($entry, PATHINFO_EXTENSION) == $ext
                ) {
                    $file[] = basename($entry, $trim_ext);
                }
            }
        }
        $d->close();

        return $file;
    }


    /**
     * 获取目录下所有的子目录
     *
     * @param string $dir
     * @param bool   $include_hide
     *
     * @return array
     */
    public static function getSubDirInDir(
        string $dir,
        bool $include_hide = false
    ) {
        if (!is_dir($dir)) {
            return [];
        }

        $sub_dir = [];

        $d = dir($dir);
        while (false !== ($entry = $d->read())) {
            if ($entry != '.' && $entry != '..') {
                if ($include_hide || (!$include_hide && $entry{0} != '.')) {
                    if (is_dir($dir.'/'.$entry)) {
                        $sub_dir[$entry] = $entry;
                    }
                }
            }
        }
        $d->close();

        return $sub_dir;
    }
}