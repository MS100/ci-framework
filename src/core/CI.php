<?php

namespace CI\core;

use CI\libraries\Cache\Cache;
use CI\libraries\DB\DB;
use CI\libraries\Redis;
use Illuminate\Container\Container;
use Illuminate\Session\Store;


/**
 * @property Benchmark $benchmark
 * @property Config $config;
 * @property Uri $uri;
 * @property Router $router;
 * @property Input $input;
 * @property Output $output;
 * @property Security $security;
 * @property Lang $lang;
 * @property Log $log;
 * @property Loader $load;
 * @property Store $session;
 * @property Hooks $hooks
 */
abstract class CI extends Container
{
    /**
     * The base path for the Laravel installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * The custom application path defined by the developer.
     *
     * @var string
     */
    protected $appPath;

    public function __construct($basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();

        self::$instance = $this;

        log_message('debug', 'SuperObject CI Initialized');

        load_class('Benchmark', 'core');
        $this->benchmark->mark('total_execution_time_start');

        load_class('Config', 'core');

        $charset = strtoupper(config_item('charset'));
        ini_set('default_charset', $charset);
        ini_set('php.internal_encoding', $charset);

        load_class('Utf8', 'core');

        load_class('Lang', 'core');

        load_class('Output', 'core');
    }

    /**
     * Set the base path for the application.
     *
     * @param string $basePath
     * @return $this
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.lang', $this->langPath());
        $this->instance('path.config', $this->configPath());
        //$this->instance('path.public', $this->publicPath());
        //$this->instance('path.storage', $this->storagePath());
        //$this->instance('path.database', $this->databasePath());
        //$this->instance('path.resources', $this->resourcePath());
        //$this->instance('path.bootstrap', $this->bootstrapPath());
    }

    /**
     * Register the basic bindings into the container.
     *
     * @return void
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(Container::class, $this);
        $this->singleton(Mix::class);

        $this->instance(
            PackageManifest::class,
            new PackageManifest(
                new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
            )
        );
    }

    /**
     * Register all of the base service providers.
     *
     * @return void
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new RoutingServiceProvider($this));
    }

    /**
     * Register the core class aliases in the container.
     *
     * @return void
     */
    public function registerCoreContainerAliases()
    {
        foreach (
            [
                'app' => [
                    self::class,
                    \Illuminate\Contracts\Container\Container::class,
                    \Illuminate\Contracts\Foundation\Application::class,
                    \Psr\Container\ContainerInterface::class
                ],
                'auth' => [\Illuminate\Auth\AuthManager::class, \Illuminate\Contracts\Auth\Factory::class],
                'auth.driver' => [\Illuminate\Contracts\Auth\Guard::class],
                'blade.compiler' => [\Illuminate\View\Compilers\BladeCompiler::class],
                'cache' => [\Illuminate\Cache\CacheManager::class, \Illuminate\Contracts\Cache\Factory::class],
                'cache.store' => [
                    \Illuminate\Cache\Repository::class,
                    \Illuminate\Contracts\Cache\Repository::class,
                    \Psr\SimpleCache\CacheInterface::class
                ],
                'cache.psr6' => [
                    \Symfony\Component\Cache\Adapter\Psr16Adapter::class,
                    \Symfony\Component\Cache\Adapter\AdapterInterface::class,
                    \Psr\Cache\CacheItemPoolInterface::class
                ],
                'config' => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
                'cookie' => [
                    \Illuminate\Cookie\CookieJar::class,
                    \Illuminate\Contracts\Cookie\Factory::class,
                    \Illuminate\Contracts\Cookie\QueueingFactory::class
                ],
                'encrypter' => [
                    \Illuminate\Encryption\Encrypter::class,
                    \Illuminate\Contracts\Encryption\Encrypter::class
                ],
                'db' => [
                    \Illuminate\Database\DatabaseManager::class,
                    \Illuminate\Database\ConnectionResolverInterface::class
                ],
                'db.connection' => [
                    \Illuminate\Database\Connection::class,
                    \Illuminate\Database\ConnectionInterface::class
                ],
                'events' => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class],
                'files' => [\Illuminate\Filesystem\Filesystem::class],
                'filesystem' => [
                    \Illuminate\Filesystem\FilesystemManager::class,
                    \Illuminate\Contracts\Filesystem\Factory::class
                ],
                'filesystem.disk' => [\Illuminate\Contracts\Filesystem\Filesystem::class],
                'filesystem.cloud' => [\Illuminate\Contracts\Filesystem\Cloud::class],
                'hash' => [\Illuminate\Hashing\HashManager::class],
                'hash.driver' => [\Illuminate\Contracts\Hashing\Hasher::class],
                'translator' => [
                    \Illuminate\Translation\Translator::class,
                    \Illuminate\Contracts\Translation\Translator::class
                ],
                'log' => [\Illuminate\Log\LogManager::class, \Psr\Log\LoggerInterface::class],
                'mailer' => [
                    \Illuminate\Mail\Mailer::class,
                    \Illuminate\Contracts\Mail\Mailer::class,
                    \Illuminate\Contracts\Mail\MailQueue::class
                ],
                'auth.password' => [
                    \Illuminate\Auth\Passwords\PasswordBrokerManager::class,
                    \Illuminate\Contracts\Auth\PasswordBrokerFactory::class
                ],
                'auth.password.broker' => [
                    \Illuminate\Auth\Passwords\PasswordBroker::class,
                    \Illuminate\Contracts\Auth\PasswordBroker::class
                ],
                'queue' => [
                    \Illuminate\Queue\QueueManager::class,
                    \Illuminate\Contracts\Queue\Factory::class,
                    \Illuminate\Contracts\Queue\Monitor::class
                ],
                'queue.connection' => [\Illuminate\Contracts\Queue\Queue::class],
                'queue.failer' => [\Illuminate\Queue\Failed\FailedJobProviderInterface::class],
                'redirect' => [\Illuminate\Routing\Redirector::class],
                'redis' => [\Illuminate\Redis\RedisManager::class, \Illuminate\Contracts\Redis\Factory::class],
                'redis.connection' => [
                    \Illuminate\Redis\Connections\Connection::class,
                    \Illuminate\Contracts\Redis\Connection::class
                ],
                'request' => [\Illuminate\Http\Request::class, \Symfony\Component\HttpFoundation\Request::class],
                'router' => [
                    \Illuminate\Routing\Router::class,
                    \Illuminate\Contracts\Routing\Registrar::class,
                    \Illuminate\Contracts\Routing\BindingRegistrar::class
                ],
                'session' => [\Illuminate\Session\SessionManager::class],
                'session.store' => [\Illuminate\Session\Store::class, \Illuminate\Contracts\Session\Session::class],
                'url' => [\Illuminate\Routing\UrlGenerator::class, \Illuminate\Contracts\Routing\UrlGenerator::class],
                'validator' => [\Illuminate\Validation\Factory::class, \Illuminate\Contracts\Validation\Factory::class],
                'view' => [\Illuminate\View\Factory::class, \Illuminate\Contracts\View\Factory::class],
            ] as $key => $aliases
        ) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Get the path to the application "app" directory.
     *
     * @param string $path
     * @return string
     */
    public function path($path = '')
    {
        $appPath = $this->appPath ?: $this->basePath . DIRECTORY_SEPARATOR . 'app';

        return $appPath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the base path of the Laravel installation.
     *
     * @param string $path Optionally, a path to append to the base path
     * @return string
     */
    public function basePath($path = '')
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param string $path Optionally, a path to append to the bootstrap path
     * @return string
     */
    /*public function bootstrapPath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'bootstrap'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }*/

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path Optionally, a path to append to the config path
     * @return string
     */
    public function configPath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the database directory.
     *
     * @param string $path Optionally, a path to append to the database path
     * @return string
     */
    /*public function databasePath($path = '')
    {
        return ($this->databasePath ?: $this->basePath.DIRECTORY_SEPARATOR.'database').($path ? DIRECTORY_SEPARATOR.$path : $path);
    }*/

    /**
     * Set the database directory.
     *
     * @param string $path
     * @return $this
     */
    /*public function useDatabasePath($path)
    {
        $this->databasePath = $path;

        $this->instance('path.database', $path);

        return $this;
    }*/

    /**
     * Get the path to the language files.
     *
     * @return string
     */
    public function langPath()
    {
        return $this->resourcePath() . DIRECTORY_SEPARATOR . 'lang';
    }

    /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    /*public function publicPath()
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'public';
    }*/

    /**
     * Get the path to the storage directory.
     *
     * @return string
     */
    /*public function storagePath()
    {
        return $this->storagePath ?: $this->basePath.DIRECTORY_SEPARATOR.'storage';
    }*/

    /**
     * Set the storage directory.
     *
     * @param string $path
     * @return $this
     */
    /*public function useStoragePath($path)
    {
        $this->storagePath = $path;

        $this->instance('path.storage', $path);

        return $this;
    }*/

    /**
     * Get the path to the resources directory.
     *
     * @param string $path
     * @return string
     */
    /*public function resourcePath($path = '')
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'resources'.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }*/


    final protected function init()
    {
        load_class('URI', 'core');

        load_class('Router', 'core');

        if (is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB)) {
            load_class('Hooks', 'core');
            load_class('Security', 'core');
        };

        load_class('Input', 'core');

        load_class('Loader', 'core');

        $this->load = $this->loader;

        $this->benchmark->mark('request_start');
        /*try {
            //即使在work中也在每个请求开始的时候加载一下，也达到刷新的目的
            CacheAppend::load();
        } catch (\Exception $e) {
            log_message('error', sprintf('%s: load configure or cache_append failed', __METHOD__));
        }*/
    }

    protected function output($data)
    {
        $this->releaseResources();

        if (isset($this->benchmark, $this->input)) {
            $this->input->set_request_info('cost', $this->benchmark->elapsed_time('request_start', 'request_end'));
        }

        $this->output->render($data);

        if ($this->output->isSuccess()) {
            log_message('info', 'request completed.');
        } else {
            log_message('info', sprintf('request failed(%s)', $this->output->getCode()));
        }
        $elapsed = $this->benchmark->elapsed_time('total_execution_time_start', 'total_execution_time_end');
        log_message('debug', 'Total execution time: ' . $elapsed);

        return $this->output->getResponse();
    }

    final protected function flushResources()
    {
        //static $_autoload_config;
        static $last_run_time;

        if (time() - $last_run_time > 300) {
            /*if (isset($this->gc) && ($this->gc instanceof \Gearman_client)) {
                $this->gc->ping();
            }*/

            Cache::keepAlive();
            Redis::keepAlive();
            $last_run_time = time();
        }
    }

    private function releaseResources()
    {
        /*if (isset($this->form_validation) && ($this->form_validation instanceof \CI_Form_validation)) {
            $this->form_validation->reset_validation();
        }*/

        DB::reset();
    }

    final public function format(string $format = '')
    {
        $this->output->format($format);
    }

    final public function getControllerDir()
    {
        return $this->getConfigTypeName() . 's' . DIRECTORY_SEPARATOR;
    }

    final public function getControllerPath()
    {
        return APP_PATH . static::getControllerDir();
    }

    final public function getConfigTypeName()
    {
        return static::CONFIG_TYPE_NAME;
    }

    final public function getAllowResponseFormat()
    {
        return static::ALLOW_RESPONSE_FORMAT;
    }
}
