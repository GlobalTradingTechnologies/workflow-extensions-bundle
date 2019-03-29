<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference;

use Psr\Container\ContainerInterface;

/**
 * Trait ContainerAwareTrait
 */
trait ContainerAwareTrait
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Sets the container.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }
}
