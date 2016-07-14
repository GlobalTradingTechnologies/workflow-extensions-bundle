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

namespace Gtt\Bundle\WorkflowExtensionsBundle\TransitionTrigger\Event;

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedTriggerEventException;
use Gtt\Bundle\WorkflowExtensionsBundle\Logger\WorkflowLoggerContextTrait;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ScheduledTransition;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\TransitionScheduler;
use Gtt\Bundle\WorkflowExtensionsBundle\SubjectManipulator;
use Gtt\Bundle\WorkflowExtensionsBundle\TransitionApplier;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Checks ability and performs transitions for configured workflows when specified event is occured 
 */
class TransitionTriggerListener
{
    use WorkflowLoggerContextTrait;

    /**
     * Expression language
     *
     * @var ExpressionLanguage
     */
    private $language;

    /**
     * Transition scheduler
     *
     * @var TransitionScheduler
     */
    private $transitionScheduler;

    /**
     * Transition applier
     *
     * @var TransitionApplier
     */
    private $transitionApplier;

    /**
     * Subject manipulator
     *
     * @var SubjectManipulator
     */
    private $subjectManipulator;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Holds listener configurations for events to be dispatched by current listener
     *
     * @var array
     */
    private $supportedEventsConfig = [];

    /**
     * TransitionTriggerListener constructor.
     *
     * @param ExpressionLanguage $language           expression language
     * @param TransitionApplier  $transitionApplier  transition applier
     * @param SubjectManipulator $subjectManipulator subject manipulator
     * @param LoggerInterface    $logger             logger
     */
    public function __construct(
        ExpressionLanguage $language,
        TransitionApplier $transitionApplier,
        SubjectManipulator $subjectManipulator,
        LoggerInterface $logger)
    {
        $this->language           = $language;
        $this->transitionApplier  = $transitionApplier;
        $this->subjectManipulator = $subjectManipulator;
        $this->logger             = $logger;
    }

    /**
     * Sets scheduler
     *
     * @param TransitionScheduler $scheduler scheduler
     */
    public function setScheduler(TransitionScheduler $scheduler)
    {
        $this->transitionScheduler = $scheduler;
    }

    /**
     * Sets configs for event to be dispatched by current listener
     *
     * @param string $eventName                   event name
     * @param string $workflowName                workflow name
     * @param string $subjectRetrievingExpression expression used to retrieve subject from event
     * @param array  $transitions                 list of transitions to try to apply by specified workflow
     */
    public function registerTriggerEvent(
        $eventName,
        $workflowName,
        $subjectRetrievingExpression,
        array $transitions = [],
        array $scheduledTransitions = [])
    {
        if (!isset($this->supportedEventsConfig[$eventName])) {
            $this->supportedEventsConfig[$eventName] = [];
        }

        $this->supportedEventsConfig[$eventName][$workflowName] = [
            'transitions'                   => $transitions,
            'scheduled_transitions'         => $this->prepareScheduledTransitions($scheduledTransitions),
            'subject_retrieving_expression' => $subjectRetrievingExpression
        ];
    }

    /**
     * Dispatches registered event
     *
     * @param Event  $event     event
     * @param string $eventName event name
     */
    public function handleEvent(Event $event, $eventName)
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

            $loggerContext = $this->getLoggerContext($workflowName, get_class($subject), $this->subjectManipulator->getSubjectId($subject));

            $this->applyTransitions($eventName, $subject, $workflowName, $eventConfigForWorkflow['transitions'], $loggerContext);
            $this->scheduleTransitions($eventName, $subject, $workflowName, $eventConfigForWorkflow['scheduled_transitions'], $loggerContext);
        }
    }

    /**
     * Builds list of ScheduledTransition objects from array inpet
     *
     * @param array $scheduledTransitions
     *
     * @return ScheduledTransition[]
     */
    private function prepareScheduledTransitions(array $scheduledTransitions = [])
    {
        $transitions = [];
        foreach ($scheduledTransitions as $transitionName => $transitionConfig) {
            $transitions[] = new ScheduledTransition($transitionName, $transitionConfig['offset']);
        }

        return $transitions;
    }

    /**
     * Retrieves workflow subject from event
     *
     * @param Event  $event                       event to be dispatched
     * @param string $eventName                   event name
     * @param string $workflowName                workflow
     * @param string $subjectRetrievingExpression expression used to retrieve subject from event
     *
     * @return object|false
     */
    private function retrieveSubjectFromEvent(Event $event, $eventName, $workflowName, $subjectRetrievingExpression)
    {
        try {
            $error = false;

            /** @var object|mixed $subject */
            $subject = $this->language->evaluate($subjectRetrievingExpression, ['event' => $event]);

            if (!is_object($subject)) {
                $error = sprintf(
                    "Subject retrieving from '%s' event by expression '%s' ended with empty or non-object result",
                    $eventName,
                    $subjectRetrievingExpression
                );
            } else {
                $this->logger->debug(sprintf('Retrieved subject from "%s" event', $eventName), ['workflow' => $workflowName]);

                return $subject;
            }
        } catch (\Exception $e) {
            $error = sprintf(
                "Cannot retrieve subject from event '%s' by evaluating expression '%s'. Error: '%s'. Please check retrieving expression",
                $eventName,
                $subjectRetrievingExpression,
                $e->getMessage()
            );
        } catch (\Throwable $e) {
            $error = sprintf(
                "Cannot retrieve subject from event '%s' by evaluating expression '%s'. Error: '%s'. Please check retrieving expression",
                $eventName,
                $subjectRetrievingExpression,
                $e->getMessage()
            );
        } finally {
            if ($error) {
                $this->logger->error($error, ['workflow' => $workflowName]);
            }
        }
    }

    /**
     * Applies configured transitions
     *
     * @param string $eventName     event name
     * @param object $subject       current subject
     * @param string $workflowName  workflow
     * @param array  $transitions   list of transitions to be applied
     * @param array  $loggerContext logger context
     */
    private function applyTransitions($eventName, $subject, $workflowName, $transitions, array $loggerContext)
    {
        try {
            $this->transitionApplier->applyTransitions($subject, $workflowName, $transitions);
        } catch (\Exception $e) {
            // Reporting failure and trying to handle event for the other workflows specified in config
            $this->logger->error(
                sprintf('Cannot apply transitions by event "%s". Details: %s', $eventName, $e->getMessage()),
                $loggerContext
            );
        } catch (\Throwable $e) {
            // Reporting failure and trying to handle event for the other workflows specified in config
            $this->logger->error(
                sprintf('Cannot apply transitions by event "%s". Details: %s', $eventName, $e->getMessage()),
                $loggerContext
            );
        }
    }

    /**
     * Schedules configured transitions
     *
     * @param string $eventName             event name
     * @param object $subject               current subject
     * @param string $workflowName          workflow
     * @param array  $scheduledTransitions  list of scheduled transitions
     * @param array  $loggerContext         logger context
     */
    private function scheduleTransitions($eventName, $subject, $workflowName, $scheduledTransitions, array $loggerContext)
    {
        try {
            if ($this->transitionScheduler) {
                // scheduling attached scheduled transitions
                $this->transitionScheduler->scheduleTransitions($subject, $workflowName, $scheduledTransitions);
            }
        } catch (\Exception $e) {
            // Reporting failure and trying to handle event for the other workflows specified in config
            $this->logger->error(
                sprintf('Cannot schedule transitions by event "%s". Details: %s', $eventName, $e->getMessage()),
                $loggerContext
            );
        } catch (\Throwable $e) {
            // Reporting failure and trying to handle event for the other workflows specified in config
            $this->logger->error(
                sprintf('Cannot schedule transitions by event "%s". Details: %s', $eventName, $e->getMessage()),
                $loggerContext
            );
        }
    }
}