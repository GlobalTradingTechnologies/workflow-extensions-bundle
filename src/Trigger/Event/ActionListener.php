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

use Gtt\Bundle\WorkflowExtensionsBundle\Action\Executor as ActionExecutor;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\ValueObject\Action;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;

/**
 * Executes action as a result of event fired
 */
class ActionListener extends AbstractActionListener
{
    /**
     * Action executor
     *
     * @var ActionExecutor
     */
    private $actionExecutor;

    /**
     * AbstractListener constructor.
     *
     * @param ExpressionLanguage $subjectRetrieverLanguage subject retriever expression language
     * @param SubjectManipulator $subjectManipulator       subject manipulator
     * @param Registry           $workflowRegistry         workflow registry
     * @param LoggerInterface    $logger                   logger
     * @param ExpressionLanguage $actionLanguage           action expression language
     * @param ActionExecutor     $actionExecutor           action executor
     */
    public function __construct(
        ExpressionLanguage $subjectRetrieverLanguage,
        SubjectManipulator $subjectManipulator,
        Registry $workflowRegistry,
        LoggerInterface $logger,
        ExpressionLanguage $actionLanguage,
        ActionExecutor $actionExecutor
    ) {
        parent::__construct($subjectRetrieverLanguage, $subjectManipulator, $workflowRegistry, $logger, $actionLanguage);
        $this->actionExecutor = $actionExecutor;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $actions list of actions to try to apply by specified workflow
     */
    public function registerEvent(
        string $eventName,
        string $workflowName,
        string $subjectRetrievingExpression,
        array $actions = []
    ): void {
        $this->configureSubjectRetrievingForEvent($eventName, $workflowName, $subjectRetrievingExpression);
        $this->supportedEventsConfig[$eventName][$workflowName]['actions'] = $actions;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleEvent(
        string $eventName,
        Event $event,
        array $eventConfigForWorkflow,
        WorkflowContext $workflowContext
    ): void {
        $actions = $this->supportedEventsConfig[$eventName][$workflowContext->getWorkflow()->getName()]['actions'];
        /** @var Action[] $actions */
        $actions = $this->prepareActions($actions, $event, $workflowContext);

        foreach ($actions as $action) {
            $this->execute(
                function () use ($workflowContext, $action) {
                    $this->actionExecutor->execute($workflowContext, $action->getName(), $action->getArguments());
                },
                $eventName,
                $workflowContext,
                sprintf('execute action "%s" with arguments "%s"', $action->getName(), json_encode($action->getArguments()))
            );
        }
    }
}
