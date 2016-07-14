<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 14.07.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\TransitionTrigger\Event;

use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ScheduledTransition;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\TransitionScheduler;
use Gtt\Bundle\WorkflowExtensionsBundle\SubjectManipulator;
use Gtt\Bundle\WorkflowExtensionsBundle\TransitionApplier;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class TransitionTriggerListenerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedTriggerEventException
     */
    public function testHandlingUnsupportedEventsTriggersException()
    {
        $listener = new TransitionTriggerListener(
            $this->getMock(ExpressionLanguage::class),
            $this->getMockBuilder(TransitionApplier::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(LoggerInterface::class)
        );

        $listener->handleEvent(new Event(), "ghost_event");
    }

    public function testUnretrievableSubjectIsReportedByLogger()
    {
        $logger = $this->getMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        list($subject, $event, $expression, $language) = $this->getSubjectEventExpressionAndLanguage(true);

        $listener = new TransitionTriggerListener(
            $language,
            $this->getMockBuilder(TransitionApplier::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $logger
        );

        $listener->registerTriggerEvent("eventName", "workflowName", $expression);
        $listener->handleEvent($event, "eventName");
    }

    public function testApplierExceptionsAreReportedByLogger()
    {
        list($subject, $event, $expression, $language) = $this->getSubjectEventExpressionAndLanguage();

        $logger = $this->getMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $applier = $this->getMockBuilder(TransitionApplier::class)->disableOriginalConstructor()->getMock();
        $applier->expects(self::once())->method('applyTransitions')->willThrowException(new \Exception());

        $listener = new TransitionTriggerListener(
            $language,
            $applier,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $logger
        );

        $listener->registerTriggerEvent("eventName", "workflowName", $expression);
        $listener->handleEvent($event, "eventName");
    }

    public function testSchedulerExceptionsAreReportedByLogger()
    {
        list($subject, $event, $expression, $language) = $this->getSubjectEventExpressionAndLanguage();

        $logger = $this->getMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $scheduler = $this->getMockBuilder(TransitionScheduler::class)->disableOriginalConstructor()->getMock();
        $scheduler->expects(self::once())->method('scheduleTransitions')->willThrowException(new \Exception());

        $listener = new TransitionTriggerListener(
            $language,
            $this->getMockBuilder(TransitionApplier::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $logger
        );

        $listener->setScheduler($scheduler);

        $listener->registerTriggerEvent("eventName", "workflowName", $expression);
        $listener->handleEvent($event, "eventName");
    }

    public function testApplierIsInvokedForTransitions()
    {
        $transitions = ['t1, t2'];
        $workflowName = "workflowName";
        list($subject, $event, $expression, $language) = $this->getSubjectEventExpressionAndLanguage();

        $applier = $this->getMockBuilder(TransitionApplier::class)->disableOriginalConstructor()->getMock();
        $applier->expects(self::once())->method('applyTransitions')->with($subject, $workflowName, $transitions);

        $listener = new TransitionTriggerListener(
            $language,
            $applier,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(LoggerInterface::class)
        );

        $listener->registerTriggerEvent("eventName", $workflowName, $expression, $transitions);
        $listener->handleEvent($event, "eventName");
    }

    public function testSchedulerIsInvokedForTransitions()
    {
        $scheduledTransitions = ['t1' => ['offset' => 'PT1S'], 't2' => ['offset' => 'PT2S']];
        $objectScheduledTransitions = [];
        foreach ($scheduledTransitions as $name => $config) {
            $objectScheduledTransitions[] = new ScheduledTransition($name, $config['offset']);
        }
        $workflowName = "workflowName";
        list($subject, $event, $expression, $language) = $this->getSubjectEventExpressionAndLanguage();

        $scheduler = $this->getMockBuilder(TransitionScheduler::class)->disableOriginalConstructor()->getMock();
        $scheduler->expects(self::once())->method('scheduleTransitions')->with($subject, $workflowName, $objectScheduledTransitions);

        $listener = new TransitionTriggerListener(
            $language,
            $this->getMockBuilder(TransitionApplier::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(LoggerInterface::class)
        );

        $listener->setScheduler($scheduler);

        $listener->registerTriggerEvent("eventName", $workflowName, $expression, [], $scheduledTransitions);
        $listener->handleEvent($event, "eventName");
    }

    public function testEventCanTriggerTransitionsFromDifferentWorkflows()
    {
        $workflowName1 = "w1";
        $workflowName2 = "w2";
        $workflowName3 = "w3";

        $subject2 = new \StdClass();
        $subject2->p = 2;
        $subject3 = new \StdClass();
        $subject3->p = 3;

        $expression1 = "e1";
        $expression2 = "e2";
        $expression3 = "e3";

        $transition1 = "t1";
        $transition2 = "t2";
        $transition3 = "t3";

        $scheduledTransition2 = ['st2' => ['offset' => 'PT1S']];
        $objectScheduledTransition2 = new ScheduledTransition('st2', 'PT1S');
        $scheduledTransition3 = ['st3' => ['offset' => 'PT2S']];
        $objectScheduledTransition3 = new ScheduledTransition('st3', 'PT2S');

        $event = new Event();
        $language = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $language->expects(self::at(0))->method('evaluate')->with($expression1, ['event' => $event])->willThrowException(new \LogicException());
        $language->expects(self::at(1))->method('evaluate')->with($expression2, ['event' => $event])->willReturn($subject2);
        $language->expects(self::at(2))->method('evaluate')->with($expression3, ['event' => $event])->willReturn($subject3);
        $language->expects($this->exactly(3))->method('evaluate');

        $scheduler = $this->getMockBuilder(TransitionScheduler::class)->disableOriginalConstructor()->getMock();
        // check that scheduler's exceptions do not stop execution
        $scheduler->expects(self::at(0))->method('scheduleTransitions')->with($subject2, $workflowName2, [$objectScheduledTransition2])->willThrowException(new \Exception());
        $scheduler->expects(self::at(1))->method('scheduleTransitions')->with($subject3, $workflowName3, [$objectScheduledTransition3]);
        $scheduler->expects($this->exactly(2))->method('scheduleTransitions');

        $applier = $this->getMockBuilder(TransitionApplier::class)->disableOriginalConstructor()->getMock();
        // check that applier's exceptions do not stop execution
        $applier->expects(self::at(0))->method('applyTransitions')->with($subject2, $workflowName2, [$transition2])->willThrowException(new \Exception());
        $applier->expects(self::at(1))->method('applyTransitions')->with($subject3, $workflowName3, [$transition3]);
        $applier->expects($this->exactly(2))->method('applyTransitions');

        $listener = new TransitionTriggerListener(
            $language,
            $applier,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(LoggerInterface::class)
        );

        $listener->setScheduler($scheduler);

        $listener->registerTriggerEvent("eventName", $workflowName1, $expression1, [$transition1]);
        $listener->registerTriggerEvent("eventName", $workflowName2, $expression2, [$transition2], $scheduledTransition2);
        $listener->registerTriggerEvent("eventName", $workflowName3, $expression3, [$transition3], $scheduledTransition3);

        $listener->handleEvent($event, "eventName");
    }

    /**
     * @return array
     */
    private function getSubjectEventExpressionAndLanguage($expressionEvaluationShouldThrowException = false)
    {
        $subject = new \StdClass();
        $event = new Event();

        $expression = "expression";
        $language = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        if ($expressionEvaluationShouldThrowException) {
            $language->expects(self::once())->method("evaluate")->with($expression, ['event' => $event])->willThrowException(new \Exception());
        } else {
            $language->expects(self::once())->method("evaluate")->with($expression, ['event' => $event])->willReturn($subject);
        }
        return array($subject, $event, $expression, $language);
    }
}
