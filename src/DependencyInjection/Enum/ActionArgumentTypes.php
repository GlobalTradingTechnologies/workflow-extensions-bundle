<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 20.09.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Enum;

/**
 * Holds available argument types for action arguments
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class ActionArgumentTypes
{
    /**
     * Scalar value
     */
    public const TYPE_SCALAR = 'scalar';

    /**
     * Expression that will be executed and result will be treated as action argument
     * Result must be scalar or non-associate array
     */
    public const TYPE_EXPRESSION = 'expression';

    /**
     * Non-associative array of arguments of other types
     */
    public const TYPE_ARRAY = 'array';
}
