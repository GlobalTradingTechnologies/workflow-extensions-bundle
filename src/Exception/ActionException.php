<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 02.08.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Exception;

use Gtt\Bundle\WorkflowExtensionsBundle\Utils\ArrayUtils;

/**
 * Action reference runtime exception
 */
class ActionException extends RuntimeException
{
    public static function containerUnavailableForServiceMethodReference(string $serviceId): self
    {
        return new static(
            sprintf(
                'Cannot retrieve object for action reference for service id "%s" due to ContainerInterface instance is not set',
                $serviceId
            )
        );
    }

    public static function actionAlreadyRegistered(string $actionName): self
    {
        return new static(sprintf('Action reference with name "%s" is already registered', $actionName));
    }

    public static function actionNotFound(string $actionName): self
    {
        return new static(sprintf('Action reference with name "%s" is not found in action registry', $actionName));
    }

    public static function actionExpressionArgumentIsMalformed(string $actionName, string $expression, $expressionResult): self
    {
        if (is_array($expressionResult) && ArrayUtils::isArrayAssoc($expressionResult)) {
            // assoc array
            $actualResultDescription = sprintf('associative array "%s"', json_encode($expressionResult));
        } else {
            // non scalar value
            $actualResultDescription = gettype($expressionResult);
        }

        return new static(
            sprintf('Action reference with name "%s" has expression-defined argument "%s" which'.
            ' result must be scalar or non-associative array. Actual result is %s',
                $actionName,
                $expression,
                $actualResultDescription
            )
        );
    }
}
