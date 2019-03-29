<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 01.09.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\ExpressionLanguage;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\Executor;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Registry;
use Gtt\Bundle\WorkflowExtensionsBundle\ExpressionLanguage\ContainerAwareExpressionLanguage;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;

/**
 * Expression language allows to use actions inside expressions
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class ActionExpressionLanguage extends ContainerAwareExpressionLanguage
{
    /**
     * {@inheritdoc}
     */
    public function __construct(
        Registry $actionRegistry,
        Executor $actionExecutor,
        ContainerInterface $container,
        CacheItemPoolInterface $cache = null,
        array $providers = []
    ) {
        parent::__construct($container, $cache, $providers);

        foreach ($actionRegistry as $actionName => $action) {
            $this->register(
                $actionName,
                static function () use ($actionName, $actionExecutor)
                {
                    $rawArgs           = func_get_args();
                    $compiledArgsArray = var_export($rawArgs, true);

                    return sprintf(
                        '$container->get("gtt.workflow.action.executor")->execute($workflowContext, "%s", %s)',
                        $actionName,
                        $compiledArgsArray
                    );
                },
                static function () use ($actionName, $actionExecutor)
                {
                    $args      = func_get_args();
                    $variables = array_shift($args);

                    return $actionExecutor->execute($variables['workflowContext'], $actionName, $args);
                }
            );
        }
    }
}
