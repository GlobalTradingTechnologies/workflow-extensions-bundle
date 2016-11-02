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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Tests\Guard;

use Gtt\Bundle\WorkflowExtensionsBundle\Guard\ExpressionGuard;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class ExpressionGuardTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedGuardEventException
     */
    public function testHandlingUnsupportedEventsTriggersException()
    {
        $guard = new ExpressionGuard(
            $this->getMock(ExpressionLanguage::class),
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(Registry::class),
            $this->getMock(LoggerInterface::class)
        );
        $event = $this->getMockBuilder(GuardEvent::class)->disableOriginalConstructor()->getMock();

        $guard->guardTransition($event, "ghost_event");
    }

    public function testGuardExpressionFailuresAreReportedByLogger()
    {
        $logger = $this->getMock(LoggerInterface::class);
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
            $this->getMockBuilder(GuardEvent::class)->disableOriginalConstructor()->getMock(),
            "eventName"
        );
    }

    public function testGuardExpressionFailuresDoNotBlocksTransition()
    {
        $logger = $this->getMock(LoggerInterface::class);
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

        $event = $this->getMockBuilder(GuardEvent::class)->disableOriginalConstructor()->getMock();
        $event->expects(self::never())->method("setBlocked");

        $guard->registerGuardExpression("eventName", "workflow", "expression");
        $guard->guardTransition($event ,"eventName");
    }

    /**
     * @dataProvider expressionProvider
     */
    public function testValidExpressionAllowsOrBlocksTransitionWithLogReport($expression, $blockTransition, $convertToBoolean = false)
    {
        $logger = $this->getMock(LoggerInterface::class);

        $language = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $language->expects(self::once())->method("evaluate")->with($expression)->willReturn($blockTransition);

        $guard = new ExpressionGuard(
            $language,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->prepareValidWorkflowRegistryMock(),
            $logger
        );

        $event = $this->getMockBuilder(GuardEvent::class)->disableOriginalConstructor()->getMock();
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

    public function expressionProvider()
    {
        return [
            ["boolenFalseExpression", false],
            ["boolenTrueExpression", true],
            // convertations to boolean
            ["convertableToTrueExpression", "1", true],
            ["convertableToFalseExpression", "0", true]
        ];
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Registry
     */
    private function prepareValidWorkflowRegistryMock()
    {
        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();
        $workflow->expects(self::once())->method('getName')->willReturn('test');
        $workflowRegistry = $this->getMock(Registry::class);
        $workflowRegistry->expects(self::once())->method('get')->willReturn($workflow);

        return $workflowRegistry;
    }
}
