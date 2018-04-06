<?php
// @codingStandardsIgnoreFile

namespace VrokPremiumTest;

use Doctrine\ORM\Tools\SchemaTool;
use Zend\Mvc\Application;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;

class Bootstrap
{
    protected static $serviceManager;
    protected static $config;
    protected static $bootstrap;

    public static function init()
    {
        define('APPLICATION_ENV', 'dev');

        // Load the user-defined test configuration file, if it exists; otherwise, load
        if (is_readable(__DIR__.'/TestConfig.local.php')) {
            $testConfig = include __DIR__.'/TestConfig.local.php';
        } else {
            $testConfig = include __DIR__.'/TestConfig.php';
        }

        $zf2ModulePaths = [];

        if (isset($testConfig['module_listener_options']['module_paths'])) {
            $modulePaths = $testConfig['module_listener_options']['module_paths'];
            foreach ($modulePaths as $modulePath) {
                if (($path = static::findParentPath($modulePath))) {
                    $zf2ModulePaths[] = $path;
                }
            }
        }

        $zf2ModulePaths = implode(PATH_SEPARATOR, $zf2ModulePaths).PATH_SEPARATOR;
        $zf2ModulePaths .= getenv('ZF2_MODULES_TEST_PATHS') ?: (
            defined('ZF2_MODULES_TEST_PATHS') ? ZF2_MODULES_TEST_PATHS : '');

        // use ModuleManager to load this module and it's dependencies
        $baseConfig = [
            'module_listener_options' => [
                'module_paths' => explode(PATH_SEPARATOR, $zf2ModulePaths),
            ],
        ];

        $config = ArrayUtils::merge($baseConfig, $testConfig);

        // ensure Module::onBootstrap is called
        $app = Application::init($config);

        static::$serviceManager = $app->getServiceManager();
        static::$config         = $config;

        static::primeDatabase();
    }

    /**
     * @return ServiceManager
     */
    public static function getServiceManager()
    {
        return static::$serviceManager;
    }

    public static function getConfig()
    {
        return static::$config;
    }

    protected static function findParentPath($path)
    {
        $dir         = __DIR__;
        $previousDir = '.';
        while (! is_dir($dir.'/'.$path)) {
            $dir = dirname($dir);
            if ($previousDir === $dir) {
                return false;
            }
            $previousDir = $dir;
        }

        return $dir.'/'.$path;
    }

    protected static function primeDatabase()
    {
        $entityManager = static::$serviceManager->get('Doctrine\ORM\EntityManager');

        // Run the schema update tool using our entity metadata
        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metadatas);
    }
}

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 'on');

require __DIR__ . '/../vendor/autoload.php';
Bootstrap::init();
