<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 14.09.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference;

/**
 * Reference to some method can be treated as an action
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
interface ActionReferenceInterface
{
    /**
     * Base default action type
     */
    public const TYPE_REGULAR = "regular";

    /**
     * Action type requires WorkflowContext instance as first argument in arguments list
     */
    public const TYPE_WORKFLOW = "workflow";

    /**
     * Returns action type
     *
     * For now can be regular or workflow
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Invokes action
     *
     * @param array $args action args
     *
     * @return mixed
     */
    public function invoke(array $args);
}
