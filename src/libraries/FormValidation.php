<?php

namespace CI\libraries;


class FormValidation extends \Ms100\FormValidation\FormValidation
{

    public static function getRequestInstance(string $group = '')
    {
        static $configs;
        if (!isset($configs)) {
            $configs = config_file('form_rules/'.ci()->getConfigTypeName());
        }

        if (empty($group)) {
            $group = ci()->router->fetch_directory_class_method();
        }

        return new self(
            $configs[$group] ?? [],
            config_item('language', 'zh'),
            APP_PATH.'form_rules/languages'
        );
    }

    /**
     * @param string $group
     *
     * @return array|bool
     * @throws \CI\core\Exceptions\FormException
     */
    public static function validatePost(string $group = '')
    {
        $fv = self::getRequestInstance($group);

        if (is_array($_FILES) && count($_FILES) > 0) {
            $_POST = array_merge($_POST, static::restructureFiles($_FILES));
            unset($_FILES);
        }

        return $fv->verify($_POST);
    }

    /**
     * @param string $group
     *
     * @return array|bool
     * @throws \CI\core\Exceptions\FormException
     */
    public static function validateGet(string $group = '')
    {
        $fv = self::getRequestInstance($group);

        return $fv->verify($_GET);
    }
}
