<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 18.07.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Guard;

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedGuardEventException;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class ExpressionGuardTest extends TestCase
{
    public function testHandlingUnsupportedEventsTriggersException(): void
    {
        $this->expectException(UnsupportedGuardEventException::class);
        $guard = new ExpressionGuard(
            $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(LoggerInterface::class)->disableOriginalConstructor()->getMock()
        );
        $event = $this->createMockedEvent();

        $guard->guardTransition($event, "ghost_event");
    }

    public function testGuardExpressionFailuresAreReportedByLogger(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMockForAbstractClass();
        $logger->expects(self::once())->method('error');

        $invalidExpression = "expression";
        $language = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $language->expects(self::once())->method("evaluate")->with($invalidExpression)->willThrowException(new \Exception());

        $guard = new ExpressionGuard(
            $language,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->prepareValidWorkflowRegistryMock(),
            $logger
        );

        $guard->registerGuardExpression("eventName", "workflow", $invalidExpression);

        $guard->guardTransition(
            $this->createMockedEvent(),
            "eventName"
        );
    }

    public function testGuardExpressionFailuresDoNotBlocksTransition(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMockForAbstractClass();
        $logger->expects(self::once())->method('error');

        $invalidExpression = "expression";
        $language = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $language->expects(self::once())->method("evaluate")->with($invalidExpression)->willThrowException(new \Exception());

        $guard = new ExpressionGuard(
            $language,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->prepareValidWorkflowRegistryMock(),
            $logger
        );

        $event = $this->createMockedEvent();
        $event->expects(self::never())->method("setBlocked");

        $guard->registerGuardExpression("eventName", "workflow", "expression");
        $guard->guardTransition($event ,"eventName");
    }

    /**
     * @dataProvider expressionProvider
     */
    public function testValidExpressionAllowsOrBlocksTransitionWithLogReport(
        string $expression,
        $expressionResult,
        bool $blockTransition,
        bool $convertToBoolean = false
    ): void {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMockForAbstractClass();

        $language = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $language->expects(self::once())->method("evaluate")->with($expression)->willReturn($expressionResult);

        $guard = new ExpressionGuard(
            $language,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->prepareValidWorkflowRegistryMock(),
            $logger
        );

        $event = $this->createMockedEvent();
        $event->expects(self::once())->method("setBlocked")->with($blockTransition);

        $loggerInvocationCount = 0;
        if ($convertToBoolean) {
            // in case of convertation logger should report about it
            $loggerInvocationCount++;
        }
        if ($blockTransition) {
            // in case of transition blocking logger should report about it
            $loggerInvocationCount++;
            $transition = $this->getMockBuilder(Transition::class)->disableOriginalConstructor()->getMock();
            $transition->expects(self::once())->method("getName");
            $event->expects(self::once())->method("getTransition")->willReturn($transition);
        }
        $logger->expects($this->exactly($loggerInvocationCount))->method("debug");

        $guard->registerGuardExpression("eventName", "workflow", $expression);
        $guard->guardTransition($event ,"eventName");
    }

    public function expressionProvider(): array
    {
        return [
            ["boolenFalseExpression", false, false],
            ["boolenTrueExpression", true, true],
            // convertations to boolean
            ["convertableToTrueExpression", "1", true, true],
            ["convertableToFalseExpression", "0", false, true],
            ["convertableToTrueExpression", 1, true, true],
            ["convertableToFalseExpression", 0, false, true],
        ];
    }

    /**
     * @return MockObject|Registry
     */
    private function prepareValidWorkflowRegistryMock(): Registry
    {
        $workflow = $this->getMockBuilder(Workflow::class)
                         ->setMethods(['getName'])
                         ->disableOriginalConstructor()
                         ->getMock();
        $workflow->expects(self::once())->method('getName')->willReturn('test');
        $workflowRegistry = $this->getMockBuilder(Registry::class)
                                 ->setMethods(['get'])
                                 ->getMockForAbstractClass();
        $workflowRegistry->expects(self::once())->method('get')->willReturn($workflow);

        return $workflowRegistry;
    }

    /**
     * @return GuardEvent|MockObject
     */
    private function createMockedEvent(): GuardEvent
    {
        $mock = $this->getMockBuilder(GuardEvent::class)
                    ->setMethods(['getSubject', 'setBlocked', 'getTransition'])
                    ->disableOriginalConstructor()
                    ->getMock();

        $mock->expects(self::any())
            ->method('getSubject')
            ->willReturn(new \stdClass());

        return $mock;
    }
}
