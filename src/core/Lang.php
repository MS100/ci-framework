<?php

namespace CI\core;

class Lang
{

    /**
     * List of translations
     *
     * @var    array
     */
    public $language = [];

    /**
     * List of loaded language files
     *
     * @var    array
     */
    public $is_loaded = [];

    /**
     * Class constructor
     *
     * @return    void
     */
    public function __construct()
    {
        log_message('debug', 'Language Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Load a language file
     *
     * @param mixed $langfile Language file name
     * @param string $idiom Language name (english, etc.)
     * @param bool $return Whether to return the loaded array of translations
     * @param bool $add_suffix Whether to add suffix to $langfile
     * @param string $alt_path Alternative path to look for the language file
     *
     * @return    void|string[]    Array containing translations, if $return is set to TRUE
     */
    public function load($langfile, $idiom = '', $return = false)
    {
        if (is_array($langfile)) {
            foreach ($langfile as $value) {
                $this->load($value, $idiom, false);
            }

            return;
        }

        $langfile .= '_lang.php';

        if (empty($idiom) OR !preg_match('/^[a-z_-]+$/i', $idiom)) {
            $idiom = config_item('language', 'zh');
        }

        if ($return === false && isset($this->is_loaded[$langfile]) && $this->is_loaded[$langfile] === $idiom) {
            return;
        }

        /*$basepath = BASEPATH . 'language/' . $idiom . '/' . $langfile;
        if (($found = file_exists($basepath)) === true) {
            include($basepath);
        }*/

        //$config = load_class('Config', 'core');
        $lang = [];
        foreach ([APP_PATH, CI_PATH] as $package_path) {
            $package_path .= 'language/' . $idiom . '/' . $langfile;
            if (file_exists($package_path)) {
                $lang += include($package_path);
                //$found = true;
            }
        }

        /*if ($found !== true) {
            log_message('notice', 'Unable to load the requested language file: language/' . $idiom . '/' . $langfile);
        }*/

        /*if (!isset($lang) OR !is_array($lang)) {
            log_message('notice', 'Language file contains no data: language/' . $idiom . '/' . $langfile);

            $lang = [];
        }*/

        if ($return === true) {
            return $lang;
        }

        $this->is_loaded[$langfile] = $idiom;
        if (!empty($lang)) {
            $this->language = array_merge($this->language, $lang);
        }

        log_message('debug', 'Language file loaded: language/' . $idiom . '/' . $langfile);
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Language line
     * Fetches a single line of text from the language array
     *
     * @param string $line Language line key
     * @param bool $log_errors Whether to log an error message if the line is not found
     *
     * @return    string    Translation
     */
    public function line($line, $log_errors = true)
    {
        $value = $this->language[$line] ?? false;

        // Because killer robots like unicorns!
        if ($value === false && $log_errors === true) {
            log_message('notice', 'Could not find the language line "' . $line . '"');
        }

        return $value;
    }

}
