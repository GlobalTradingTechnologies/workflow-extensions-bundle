<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 17.07.15
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle;

use Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Compiler\CheckSubjectManipulatorConfigPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle class
 */
class WorkflowExtensionsBundle extends Bundle
{    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new CheckSubjectManipulatorConfigPass());
    }
}
