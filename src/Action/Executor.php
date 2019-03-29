<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 23.09.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ActionReferenceInterface;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ContainerAwareInterface;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Psr\Container\ContainerInterface;

/**
 * Action executor
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class Executor
{
    /**
     * Action registry
     *
     * @var Registry
     */
    private $registry;

    /**
     * Container DI
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * Executor constructor.
     *
     * @param Registry           $registry  action registry
     * @param ContainerInterface $container container DI
     */
    public function __construct(Registry $registry, ContainerInterface $container)
    {
        $this->registry  = $registry;
        $this->container = $container;
    }

    /**
     * Executes action
     *
     * @param WorkflowContext $workflowContext workflow context
     * @param string          $actionName      action name
     * @param array           $args            action arguments
     *
     * @return mixed
     */
    public function execute(WorkflowContext $workflowContext, string $actionName, array $args = [])
    {
        $actionReference = $this->registry->get($actionName);

        if ($actionReference->getType() === ActionReferenceInterface::TYPE_WORKFLOW) {
            array_unshift($args, $workflowContext);
        }

        if ($actionReference instanceof ContainerAwareInterface) {
            // container should be injected to allow action to use service as an method owner
            $actionReference->setContainer($this->container);
        }

        return $actionReference->invoke($args);
    }
}
