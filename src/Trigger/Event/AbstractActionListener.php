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
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Trigger\Event;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\ValueObject\Action;
use Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Enum\ActionArgumentTypes;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\ActionException;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ValueObject\ScheduledAction;
use Gtt\Bundle\WorkflowExtensionsBundle\Utils\ArrayUtils;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;

/**
 * Abstract implementation for all action-related listeners
 */
abstract class AbstractActionListener extends AbstractListener
{
    /**
     * Expression language for execute expressions with actions
     *
     * @var ExpressionLanguage
     */
    private $actionLanguage;

    /**
     * AbstractListener constructor.
     *
     * @param ExpressionLanguage $subjectRetrieverLanguage subject retriever expression language
     * @param SubjectManipulator $subjectManipulator       subject manipulator
     * @param Registry           $workflowRegistry         workflow registry
     * @param LoggerInterface    $logger                   logger
     * @param ExpressionLanguage $actionLanguage           action expression language
     */
    public function __construct(
        ExpressionLanguage $subjectRetrieverLanguage,
        SubjectManipulator $subjectManipulator,
        Registry $workflowRegistry,
        LoggerInterface $logger,
        ExpressionLanguage $actionLanguage
    ) {
        parent::__construct($subjectRetrieverLanguage, $subjectManipulator, $workflowRegistry, $logger);
        $this->actionLanguage = $actionLanguage;
    }

    /**
     * Composes Action instances based on bundle actions config
     *
     * @param array           $actions         list of action names associated with action arguments
     * @param Event           $event           current event to be handled
     * @param WorkflowContext $workflowContext workflow context
     * @param bool            $scheduled       flag defines whether current action is scheduled one or not
     *
     * @return ScheduledAction[]|Action[]
     */
    protected function prepareActions(
        array $actions,
        Event $event,
        WorkflowContext $workflowContext,
        bool $scheduled = false
    ): array {
        $preparedActions = [];

        foreach ($actions as $actionName => $actionCalls) {
            foreach ($actionCalls as $actionCall) {
                $actionArguments = $this->resolveActionArguments($actionName, $actionCall['arguments'], $event, $workflowContext);

                if ($scheduled) {
                    $preparedActions[] = new ScheduledAction($actionName, $actionArguments, $actionCall['offset'], $actionCall['reschedulable']);
                } else {
                    $preparedActions[] = new Action($actionName, $actionArguments);
                }
            }
        }

        return $preparedActions;
    }

    /**
     * Resolves action arguments
     *
     * NOTE: the input array can be assoc here (but keys would be replaced with sequences)
     * because associativity is validated in Configuration.php already
     * (@see \Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Configuration::buildActionArgumentsNode)
     * Expression results must be non-assoc since expressions are evaluated in runtime
     *
     * @param string          $actionName      action name
     * @param array           $arguments       list of raw action arguments
     * @param Event           $event           event instance
     * @param WorkflowContext $workflowContext workflow context
     *
     * @return array
     */
    private function resolveActionArguments(
        $actionName,
        array $arguments,
        Event $event,
        WorkflowContext $workflowContext
    ): array {
        $result = [];
        foreach ($arguments as $argument) {
            switch ($argument['type']) {
                case ActionArgumentTypes::TYPE_SCALAR:
                    $result[] = $argument['value'];
                    break;
                case ActionArgumentTypes::TYPE_EXPRESSION:
                    $expressionResult = $this->actionLanguage->evaluate($argument['value'], ['event' => $event, 'workflowContext' => $workflowContext]);
                    $isNonAssocArrayResult = is_array($expressionResult) && !ArrayUtils::isArrayAssoc($expressionResult);
                    // expression result should be scalar or non assoc Array
                    if (!($expressionResult === null || \is_scalar($expressionResult) || $isNonAssocArrayResult)) {
                        throw ActionException::actionExpressionArgumentIsMalformed($actionName, $argument['value'], $expressionResult);
                    }
                    $result[] = $expressionResult;
                    break;
                case ActionArgumentTypes::TYPE_ARRAY:
                    $result[] = $this->resolveActionArguments($actionName, $argument['value'], $event, $workflowContext);
                    break;
            }
        }

        return $result;
    }
}
