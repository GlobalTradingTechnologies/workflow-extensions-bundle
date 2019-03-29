<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Author: Alex Medvedev
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional\Kernel;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Kernel for functional / integration tests
 */
class BaseTestKernel extends Kernel implements TestKernelInterface
{
    /**
     * Directory name where kernel configs are stored
     *
     * @var string
     */
    protected $testCase;

    /**
     * Root directory of the kernel. Contains testCase directories
     *
     * @var string
     */
    protected $rootConfig;

    /**
     * Name of the application config
     *
     * @var string
     */
    protected $configDir;

    /**
     * Raw kernel name used for generating kernel directories (cache, logs etc)
     * General name should contain information about test case in order to
     * generate unique cache classes for all th test cases
     *
     * @var string
     */
    protected $rawName;

    /**
     * If $rootDir is not provided, it would be set to $configDir
     * Attention! If $rootDir is provided but is not exists, it would be created
     * just like cacheDir and logDir
     *
     * {@inheritdoc}
     */
    public function setTestKernelConfiguration(
        string $appName,
        string $testCase,
        string $configDir,
        string $rootConfig,
        string $rootDir = null
    ): void {
        if (!$rootDir) {
            $rootDir = $configDir;
        }

        $this->setRootDir($rootDir);

        $this->configDir = realpath($configDir);
        if (!is_dir($this->configDir . '/' . $testCase)) {
            throw new \InvalidArgumentException(sprintf('The test case "%s" does not exist.', $testCase));
        }
        $this->testCase = $testCase;

        $fs = new Filesystem();
        if (!$fs->isAbsolutePath($rootConfig) &&
            !file_exists($rootConfig = $this->configDir . '/' . $testCase . '/' . $rootConfig)) {
            throw new \InvalidArgumentException(sprintf('The root config "%s" does not exist.', $rootConfig));
        }
        $this->rootConfig = $rootConfig;
        $this->rawName    = $appName;
        $this->name       = preg_replace('/[^a-zA-Z0-9_]+/', '', $this->rawName)."_".
                            preg_replace('/[^a-zA-Z0-9_]+/', '', str_replace(DIRECTORY_SEPARATOR, "_", $testCase));
    }

    /**
     * Sets kernel root directory
     *
     * @param string $rootDir kernel root dir
     *
     * @throws \RuntimeException if directory does not exist and can not be created
     *
     * @return void
     */
    protected function setRootDir($rootDir)
    {
        if (!is_dir($rootDir) && !mkdir($rootDir, 0777, true) && !is_dir($rootDir)) {
            throw new \RuntimeException(sprintf('Unable to create test kernel root directory %s ', $rootDir));
        }
        $this->rootDir = realpath($rootDir);
    }

    /**
     * {@inheritdoc}
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }

    /**
     * Returns base bundle list
     *
     * @return array
     */
    protected function getBaseBundles()
    {
        return array(
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        $bundles     = array();
        $baseBundles = $this->getBaseBundles();
        if (file_exists($filename = $this->configDir . '/' . $this->testCase . '/bundles.php')) {
            $bundles = include $filename;
        }
        return array_merge($baseBundles, $bundles);
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return $this->getTempAppDir()."/".$this->testCase.'/cache/'.$this->environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir()
    {
        return $this->getTempAppDir()."/".$this->testCase.'/logs';
    }

    /**
     * {@inheritdoc}
     */
    public function getTempAppDir(): string
    {
        return sys_get_temp_dir().'/'.$this->rawName;
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->rootConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(
            array(
                $this->getEnvironment(),
                $this->isDebug(),
                $this->rawName,
                $this->testCase,
                $this->configDir,
                $this->rootConfig,
                $this->rootDir));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($str)
    {
        [$env, $debug, $appName, $testCase, $configDir, $rootConfig, $rootDir] = unserialize($str, ['allow_classes' => false]);
        $this->__construct($env, $debug);
        $this->setTestKernelConfiguration($appName, $testCase, $configDir, $rootConfig, $rootDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function getKernelParameters()
    {
        $parameters = parent::getKernelParameters();

        $parameters['kernel.test_case']  = $this->testCase;
        $parameters['kernel.config_dir'] = $this->configDir;

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        if ($this->name === null) {
            return static::class;
        }

        return parent::getName();
    }
}
