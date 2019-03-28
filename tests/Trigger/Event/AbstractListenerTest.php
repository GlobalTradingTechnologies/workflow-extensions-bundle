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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Trigger\Event;

use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ScheduledTransition;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\TransitionScheduler;
use Gtt\Bundle\WorkflowExtensionsBundle\TestCase;
use Gtt\Bundle\WorkflowExtensionsBundle\TransitionApplier;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class AbstractListenerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedTriggerEventException
     */
    public function testHandlingUnsupportedEventsTriggersException()
    {
        /** @var AbstractListener $listener */
        $listener = self::getMockForAbstractClass(AbstractListener::class, [], "", false);
        $listener->dispatchEvent(new Event(), "ghost_event");
    }

    public function testUnretrievableSubjectIsReportedByLogger()
    {
        $logger = $this->getMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        list($event, $expression, $language) = $this->getEventExpressionAndLanguage(true);

        /** @var AbstractListener $listener */
        $listener = self::getMockForAbstractClass(
            AbstractListener::class,
            [
                $language,
                $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
                $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock(),
                $logger
            ]
        );

        $configureEventMethodRef = new ReflectionMethod($listener, 'configureSubjectRetrievingForEvent');
        $configureEventMethodRef->setAccessible(true);
        $configureEventMethodRef->invokeArgs($listener, ['eventName', 'workflowName', $expression]);

        $listener->dispatchEvent($event, "eventName");
    }

    public function testSupportedEventIsHandled()
    {
        list($event, $expression, $language) = $this->getEventExpressionAndLanguage();
        $eventName = "eventName";

        $workflowRegistry = $this->getMock(Registry::class);
        $workflowRegistry->expects(self::once())->method('get')->willReturn(
            $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock()
        );

        /** @var AbstractListener|PHPUnit_Framework_MockObject_MockObject $listener */
        $listener = self::getMockForAbstractClass(
            AbstractListener::class,
            [
                $language,
                $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
                $workflowRegistry,
                $this->getMock(LoggerInterface::class)
            ]
        );

        $listener
            ->expects(self::once())
            ->method('handleEvent')
            ->with(
                self::equalTo($eventName),
                self::equalTo($event),
                // event config
                self::isType('array'),
                self::isInstanceOf(WorkflowContext::class)
            )
        ;

        $configureEventMethodRef = new ReflectionMethod($listener, 'configureSubjectRetrievingForEvent');
        $configureEventMethodRef->setAccessible(true);
        $configureEventMethodRef->invokeArgs($listener, ['eventName', 'workflowName', $expression]);

        $listener->dispatchEvent($event, $eventName);
    }

    /**
     * @return array
     */
    private function getEventExpressionAndLanguage($expressionEvaluationShouldThrowException = false)
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
        return array($event, $expression, $language);
    }
}
