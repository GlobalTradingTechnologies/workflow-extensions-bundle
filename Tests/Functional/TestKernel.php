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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Tests\Functional;

use Gtt\Bundle\Core\Tests\Kernel\BaseTestKernel;

class TestKernel extends BaseTestKernel
{
    /**
     * Reducing list of required bundles as much as possible
     *
     * @return array
     */
    protected function getBaseBundles()
    {
        return array(
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Symfony\Bundle\MonologBundle\MonologBundle(),
            new \Gtt\Bundle\Core\GttCoreBundle(),

            new \Symfony\Bundle\WorkflowBundle\WorkflowBundle(),
        );
    }
}