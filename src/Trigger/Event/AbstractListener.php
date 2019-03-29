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

use Exception;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedTriggerEventException;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;
use Throwable;

/**
 * Holds base functionality for all workflow event listeners
 */
abstract class AbstractListener
{
    /**
     * Holds listener configurations for events to be dispatched by current listener
     *
     * @var array
     */
    protected $supportedEventsConfig = [];

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Expression language for retrieving subject from event
     *
     * @var ExpressionLanguage
     */
    private $subjectRetrieverLanguage;

    /**
     * Subject manipulator
     *
     * @var SubjectManipulator
     */
    private $subjectManipulator;

    /**
     * Workflow registry
     *
     * @var Registry
     */
    private $workflowRegistry;

    /**
     * AbstractListener constructor.
     *
     * @param ExpressionLanguage $subjectRetrieverLanguage subject retriever expression language
     * @param SubjectManipulator $subjectManipulator       subject manipulator
     * @param Registry           $workflowRegistry         workflow registry
     * @param LoggerInterface    $logger                   logger
     */
    public function __construct(
        ExpressionLanguage $subjectRetrieverLanguage,
        SubjectManipulator $subjectManipulator,
        Registry $workflowRegistry,
        LoggerInterface $logger
    ) {
        $this->subjectRetrieverLanguage = $subjectRetrieverLanguage;
        $this->subjectManipulator = $subjectManipulator;
        $this->workflowRegistry   = $workflowRegistry;
        $this->logger             = $logger;
    }

    /**
     * Sets configs for event to be dispatched by current listener
     *
     * @param string $eventName                   event name
     * @param string $workflowName                workflow name
     * @param string $subjectRetrievingExpression expression used to retrieve subject from event
     */
    protected function configureSubjectRetrievingForEvent(
        string $eventName,
        string $workflowName,
        string $subjectRetrievingExpression
    ): void {
        if (!isset($this->supportedEventsConfig[$eventName])) {
            $this->supportedEventsConfig[$eventName] = [];
        }

        $this->supportedEventsConfig[$eventName][$workflowName] = [
            'subject_retrieving_expression' => $subjectRetrievingExpression
        ];
    }

    /**
     * Dispatches registered event
     *
     * @param Event  $event     event
     * @param string $eventName event name
     */
    final public function dispatchEvent(Event $event, string $eventName): void
    {
        if (!array_key_exists($eventName, $this->supportedEventsConfig)) {
            throw new UnsupportedTriggerEventException(sprintf("Cannot find registered trigger event by name '%s'", $eventName));
        }

        foreach ($this->supportedEventsConfig[$eventName] as $workflowName => $eventConfigForWorkflow) {
            $subjectRetrievingExpression = $eventConfigForWorkflow['subject_retrieving_expression'];
            $subject = $this->retrieveSubjectFromEvent($event, $eventName, $workflowName, $subjectRetrievingExpression);
            if (!$subject) {
                continue;
            }

            $this->handleEvent(
                $eventName,
                $event,
                $eventConfigForWorkflow,
                $this->getWorkflowContext($subject, $workflowName)
            );
        }
    }

    /**
     * Reacts on the event occurred with some activity
     *
     * @param string          $eventName              event name
     * @param Event           $event                  event instance
     * @param array           $eventConfigForWorkflow registered config for particular event handling
     * @param WorkflowContext $workflowContext        workflow context
     *
     * @return void
     */
    abstract protected function handleEvent(string $eventName, Event $event, array $eventConfigForWorkflow, WorkflowContext $workflowContext): void;

    /**
     * Allows to execute any listener callback with control of internal errors and exceptions handling.
     * For now all the errors and exceptions are logged and rethrown.
     * There is an ability to configure exception catching in the future in order to make possible next execution.
     *
     * @param \Closure        $closure         closure to be executed safely
     * @param string          $eventName       event name
     * @param WorkflowContext $workflowContext workflow context
     * @param string          $activity        description of the current listener activity (required for logging
     *                                         purposes)
     *
     * @throws Exception|Throwable in case of failure
     */
    protected function execute(
        \Closure $closure,
        string $eventName,
        WorkflowContext $workflowContext,
        string $activity = 'react'
    ): void {
        try {
            $closure();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Cannot %s on event "%s" due to exception. Details: %s', $activity, $eventName, $e->getMessage()),
                $workflowContext->getLoggerContext()
            );
            throw $e;
        } catch (Throwable $e) {
            $this->logger->critical(
                sprintf('Cannot %s on event "%s" due to error. Details: %s', $activity, $eventName, $e->getMessage()),
                $workflowContext->getLoggerContext()
            );
            throw $e;
        }
    }

    /**
     * Retrieves workflow subject from event
     *
     * @param Event  $event                       event to be dispatched
     * @param string $eventName                   event name
     * @param string $workflowName                workflow
     * @param string $subjectRetrievingExpression expression used to retrieve subject from event
     *
     * @return object|null
     */
    private function retrieveSubjectFromEvent(Event $event, string $eventName, string $workflowName, string $subjectRetrievingExpression)
    {
        try {
            /** @var object|mixed $subject */
            $subject = $this->subjectRetrieverLanguage->evaluate($subjectRetrievingExpression, ['event' => $event]);

            if (!\is_object($subject)) {
                $error = sprintf(
                    "Subject retrieving from '%s' event by expression '%s' ended with empty or non-object result",
                    $eventName,
                    $subjectRetrievingExpression
                );
                $this->logger->error($error, ['workflow' => $workflowName]);
            }

            $this->logger->debug(sprintf('Retrieved subject from "%s" event', $eventName), ['workflow' => $workflowName]);

            return $subject;
        } catch (Throwable $e) {
            $error = sprintf(
                "Cannot retrieve subject from event '%s' by evaluating expression '%s'. Error: '%s'. Please check retrieving expression",
                $eventName,
                $subjectRetrievingExpression,
                $e->getMessage()
            );

            $this->logger->error($error, ['workflow' => $workflowName]);

            return null;
        }
    }

    /**
     * Creates workflow context
     *
     * @param object $subject      workflow subject
     * @param string $workflowName workflow name
     *
     * @return WorkflowContext
     */
    private function getWorkflowContext($subject, string $workflowName): WorkflowContext
    {
        $workflowContext = new WorkflowContext(
            $this->workflowRegistry->get($subject, $workflowName),
            $subject,
            $this->subjectManipulator->getSubjectId($subject)
        );

        return $workflowContext;
    }
}
