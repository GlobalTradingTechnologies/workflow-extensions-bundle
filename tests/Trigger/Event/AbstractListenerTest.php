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
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Trigger\Event;

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedTriggerEventException;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class AbstractListenerTest extends TestCase
{
    public function testHandlingUnsupportedEventsTriggersException(): void
    {
        $this->expectException(UnsupportedTriggerEventException::class);
        /** @var AbstractListener $listener */
        $listener = self::getMockForAbstractClass(AbstractListener::class, [], "", false);
        $listener->dispatchEvent(new Event(), "ghost_event");
    }

    public function testUnretrievableSubjectIsReportedByLogger()
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMockForAbstractClass();
        $logger->expects(self::once())->method('error');

        [$event, $expression, $language] = $this->getEventExpressionAndLanguage(true);

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

    public function testSupportedEventIsHandled(): void
    {
        [$event, $expression, $language] = $this->getEventExpressionAndLanguage();
        $eventName = "eventName";

        $workflowRegistry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $workflowRegistry->expects(self::once())->method('get')->willReturn(
            $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock()
        );

        /** @var AbstractListener|MockObject $listener */
        $listener = self::getMockForAbstractClass(
            AbstractListener::class,
            [
                $language,
                $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
                $workflowRegistry,
                $this->getMockBuilder(LoggerInterface::class)->getMockForAbstractClass()
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
    private function getEventExpressionAndLanguage(bool $expressionEvaluationShouldThrowException = false): array
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
