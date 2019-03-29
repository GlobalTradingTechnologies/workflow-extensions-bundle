<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Author: Alex Medvedev
 * Date: 16.05.12
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional\Kernel;

/**
 * Describes base functionality for Kernel classes used in functional / integration tests
 *
 * (c) fduch <alex.medwedew@gmail.com>
 */
interface TestKernelInterface
{
    /**
     * Sets Kernel configuration
     *
     * @param string      $appName    name of test application kernel
     * @param string      $testCase   directory name where kernel configs are stored
     * @param string      $configDir  path to directory with test cases configurations
     * @param string      $rootConfig name of the application config file
     * @param string|null $rootDir    path to root directory of test application. Can be unset
     *                                due to backward compatibility.
     *
     * @return void
     */
    public function setTestKernelConfiguration(
        string $appName,
        string $testCase,
        string $configDir,
        string $rootConfig,
        string $rootDir = null
    ): void;

    /**
     * Returns temporary application folder that is used to store cache, logs of test kernel.
     * This folder is always clear after tests run thanks to
     * \Gtt\Bundle\WorkflowExtensionsBundle\Functional\TestCase::tearDownAfterClass - you can always
     * change this behaviour by overriding this method.
     * Can be used to to store other temporary application data. For example can be used to construct
     * root directory if functional tests generate some file stuff that depends on $kernel->getRootDir() folder
     *
     * @return string
     */
    public function getTempAppDir(): string;
}
