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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\ExpressionLanguage;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\Registry;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Executor;
use Gtt\Bundle\WorkflowExtensionsBundle\ExpressionLanguage\ContainerAwareExpressionLanguage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface;

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
        ParserCacheInterface $cache = null,
        array $providers = array())
    {
        parent::__construct($container, $cache, $providers);

        foreach ($actionRegistry as $actionName => $action) {
            $this->register(
                $actionName,
                function () use ($actionName, $actionExecutor)
                {
                    $rawArgs           = func_get_args();
                    $compiledArgsArray = "array(". implode(", ", $rawArgs) . ")";

                    return sprintf(
                        '$container->get("gtt.workflow.action.executor")->get("%s", $workflowContext, %s)',
                        $actionName,
                        $compiledArgsArray
                    );
                },
                function () use ($actionName, $actionExecutor)
                {
                    $args      = func_get_args();
                    $variables = array_shift($args);

                    return $actionExecutor->execute($variables['workflowContext'], $actionName, $args);
                }
            );
        }
    }
}
