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

use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ActionScheduler;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ValueObject\ScheduledAction;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;

/**
 * Schedules action as a result of event fired
 */
class SchedulerListener extends AbstractActionListener
{
    /**
     * Action scheduler
     *
     * @var ActionScheduler
     */
    private $actionScheduler;

    /**
     * AbstractListener constructor.
     *
     * @param ExpressionLanguage $subjectRetrieverLanguage subject retriever expression language
     * @param SubjectManipulator $subjectManipulator       subject manipulator
     * @param Registry           $workflowRegistry         workflow registry
     * @param LoggerInterface    $logger                   logger
     * @param ExpressionLanguage $actionLanguage           action expression language
     * @param ActionScheduler    $actionScheduler          action scheduler
     */
    public function __construct(
        ExpressionLanguage $subjectRetrieverLanguage,
        SubjectManipulator $subjectManipulator,
        Registry $workflowRegistry,
        LoggerInterface $logger,
        ExpressionLanguage $actionLanguage,
        ActionScheduler $actionScheduler)
    {
        parent::__construct($subjectRetrieverLanguage, $subjectManipulator, $workflowRegistry, $logger, $actionLanguage);
        $this->actionScheduler = $actionScheduler;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $expression expression to be executed by event
     */
    public function registerEvent(
        string $eventName,
        string $workflowName,
        string $subjectRetrievingExpression,
        array $scheduledActions = [])
    {
        $this->configureSubjectRetrievingForEvent($eventName, $workflowName, $subjectRetrievingExpression);
        $this->supportedEventsConfig[$eventName][$workflowName]['scheduled_actions'] = $scheduledActions;
    }


    /**
     * {@inheritdoc}
     */
    protected function handleEvent(string $eventName, Event $event, array $eventConfigForWorkflow, WorkflowContext $workflowContext): void
    {
        $actions = $this->supportedEventsConfig[$eventName][$workflowContext->getWorkflow()->getName()]['scheduled_actions'];
        /** @var ScheduledAction[] $actions */
        $actions = $this->prepareActions($actions, $event, $workflowContext, true);

        foreach ($actions as $scheduledAction) {
            $this->execute(
                function () use ($workflowContext, $scheduledAction) {
                    $this->actionScheduler->scheduleAction($workflowContext, $scheduledAction);
                },
                $eventName,
                $workflowContext,
                sprintf(
                    'schedule action "%s" with arguments "%s"',
                    $scheduledAction->getName(),
                    json_encode($scheduledAction->getArguments())
                )
            );
        }
    }
}
