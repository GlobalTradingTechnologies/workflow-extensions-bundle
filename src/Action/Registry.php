<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 31.08.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action;

use ArrayIterator;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ActionReferenceInterface;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\ActionException;
use IteratorAggregate;

/**
 * Workflow action registry
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class Registry implements IteratorAggregate
{
    /**
     * List of workflow actions
     *
     * @var ActionReferenceInterface[]
     */
    private $actions = [];

    /**
     * Registry constructor.
     *
     * @param array $actions list of action names associated to action references
     */
    public function __construct(array $actions = [])
    {
        foreach ($actions as $actionName => $actionReference) {
            $this->add($actionName, $actionReference);
        }
    }

    /**
     * Registers action by name in repository
     *
     * @param string                   $actionName action name
     * @param ActionReferenceInterface $action     action reference
     */
    public function add($actionName, ActionReferenceInterface $action): void
    {
        if (array_key_exists($actionName, $this->actions)) {
            throw ActionException::actionAlreadyRegistered($actionName);
        }
        
        $this->actions[$actionName] = $action;
    }

    /**
     * Returns ActionInterface by name
     *
     * @param string $actionName action name
     *
     * @return ActionReferenceInterface
     */
    public function get(string $actionName): ActionReferenceInterface
    {
        if (!array_key_exists($actionName, $this->actions)) {
            throw ActionException::actionNotFound($actionName);
        }

        return $this->actions[$actionName];
    }

    public function getIterator()
    {
        return new ArrayIterator($this->actions);
    }
}
