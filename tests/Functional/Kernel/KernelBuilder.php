<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Author: Alex Medvedev
 * Date: 15.05.12
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional\Kernel;

/**
 * Builds test kernel instance
 *
 * (c) fduch <alex.medwedew@gmail.com>
 */
class KernelBuilder
{
    /**
     * Defines default test kernel class name
     *
     * @var string
     */
    protected static $defaultTestKernelClass = '\Gtt\Bundle\WorkflowExtensionsBundle\Functional\Kernel\BaseTestKernel';

    /**
     * Returns kernel instance
     *
     * @param string $kernelClass kernel class name need to be instantiated
     * @param array $options kernel configuration options
     *     base options:
     *         environment - environment
     *         debug - debug mode
     *     test kernel specific options (for TestKernelInterface instances)
     *         app_name - name of test application kernel
     *         test_case - directory name where kernel configs are stored
     *         config_dir - path to directory with test cases configurations
     *         root_config - name of the application config file
     *         root_dir - path to root directory of test application. Can be unset
     *
     * @throws \InvalidArgumentException in case of invalid options specified
     *
     * @return \Symfony\Component\HttpKernel\KernelInterface
     */
    public static function getKernel($kernelClass = null, array $options = array())
    {
        if (!($kernelClass === null)) {
            if (!class_exists($kernelClass)) {
                throw new \InvalidArgumentException("Cannot load $kernelClass");
            }
        } else {
            $kernelClass = self::$defaultTestKernelClass;
        }

        if (defined("KERNEL_ENV")) {
            $options['environment'] = KERNEL_ENV;
        }

        if (defined("KERNEL_DEBUG")) {
            $options['debug'] = KERNEL_DEBUG;
        }

        $kernel = new $kernelClass(
            isset($options['environment']) ? $options['environment'] : 'test',
            isset($options['debug']) ? $options['debug'] : true
        );

        if ($kernel instanceof TestKernelInterface) {
            if (!isset($options['app_name'])) {
                throw new \InvalidArgumentException('The option "app_name" must be set.');
            }
            if (!isset($options['config_dir'])) {
                throw new \InvalidArgumentException('The option "config_dir" must be set.');
            }
            if (!isset($options['test_case'])) {
                throw new \InvalidArgumentException('The option "test_case" must be set.');
            }

            $kernel->setTestKernelConfiguration(
                $options['app_name'],
                $options['test_case'],
                $options['config_dir'],
                $options['root_config'] ?? 'config.yml',
                $options['root_dir'] ?? null
            );
        }

        return $kernel;
    }
}
