<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 29.06.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional;

use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Kernel\KernelBuilder;
use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Kernel\TestKernelInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Filesystem\Filesystem;

class TestCase extends BaseWebTestCase
{
    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass(): void
    {
        static::deleteTmpDirs();
    }

    /**
     * {@inheritdoc}
     */
    protected static function getKernelClass()
    {
        if (defined("KERNEL_CLASS")) {
            return KERNEL_CLASS;
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected static function createKernel(array $options = array())
    {
        return KernelBuilder::getKernel(static::getKernelClass(), $options);
    }

    /**
     * Clears temporary test kernel application folder in case of kernel implements TestKernelInterface
     * @see TestKernelInterface::getTempAppDir
     *
     * @return void
     */
    protected static function deleteTmpDirs(): void
    {
        if (static::$kernel) {
            $kernel = static::$kernel;
            $fs = new Filesystem();
            if ($kernel instanceof TestKernelInterface) {
                $fs->remove($kernel->getTempAppDir());
            }
        }
    }
}
