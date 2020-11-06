<?php
namespace CI\core;

class Hooks extends \CI_Hooks
{
    public function __construct()
    {
        $CFG =& load_class('Config', 'core');
        log_message('debug', 'Hooks Class Initialized');

        // If hooks are not enabled in the config file
        // there is nothing else to do
        if ($CFG->item('enable_hooks') === FALSE)
        {
            return;
        }

        // Grab the "hooks" definition file.
        if (file_exists(APP_PATH.'config/hooks.php'))
        {
            include(APP_PATH.'config/hooks.php');
        }

        if (file_exists(APP_PATH.'config/'.ENVIRONMENT.'/hooks.php'))
        {
            include(APP_PATH.'config/'.ENVIRONMENT.'/hooks.php');
        }

        // If there are no hooks, we're done.
        if ( ! isset($hook) OR ! is_array($hook))
        {
            return;
        }

        $this->hooks =& $hook;
        $this->enabled = TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * Run Hook
     * Runs a particular hook
     *
     * @param    array $data Hook details
     *
     * @return    bool    TRUE on success or FALSE on failure
     */
    protected function _run_hook($data)
    {
        // Closures/lambda functions and array($object, 'method') callables
        if (is_callable($data)) {
            /**
             * 修改了这里的调用方式，使得可以调用静态方法
             */
            call_user_func($data);

            /*is_array($data)
                ? $data[0]->{$data[1]}()
                : $data();*/

            return true;
        } elseif (!is_array($data)) {
            return false;
        }

        // -----------------------------------
        // Safety - Prevents run-away loops
        // -----------------------------------

        // If the script being called happens to have the same
        // hook call within it a loop can happen
        if ($this->_in_progress === true) {
            return;
        }

        // -----------------------------------
        // Set file path
        // -----------------------------------

        if (!isset($data['filepath'], $data['filename'])) {
            return false;
        }

        $filepath = APP_PATH . $data['filepath'] . '/' . $data['filename'];

        if (!file_exists($filepath)) {
            return false;
        }

        // Determine and class and/or function names
        $class = empty($data['class']) ? false : $data['class'];
        $function = empty($data['function']) ? false : $data['function'];
        $params = $data['params'] ?? '';

        if (empty($function)) {
            return false;
        }

        // Set the _in_progress flag
        $this->_in_progress = true;

        // Call the requested class and/or function
        if ($class !== false) {
            // The object is stored?
            if (isset($this->_objects[$class])) {
                if (method_exists($this->_objects[$class], $function)) {
                    $this->_objects[$class]->$function($params);
                } else {
                    return $this->_in_progress = false;
                }
            } else {
                class_exists($class, false) OR require_once($filepath);

                if (!class_exists($class, false) OR !method_exists($class, $function)) {
                    return $this->_in_progress = false;
                }

                // Store the object and execute the method
                $this->_objects[$class] = new $class();
                $this->_objects[$class]->$function($params);
            }
        } else {
            function_exists($function) OR require_once($filepath);

            if (!function_exists($function)) {
                return $this->_in_progress = false;
            }

            $function($params);
        }

        $this->_in_progress = false;
        return true;
    }

}
