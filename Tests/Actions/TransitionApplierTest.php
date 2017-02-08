<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date   08.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Tests\Actions;

use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Gtt\Bundle\WorkflowExtensionsBundle\Actions\TransitionApplier;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Workflow;

class TransitionApplierTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider exceptionAndLogLevelProvider
     */
    public function testUnavailabilityToPerformTransitionIsReportedByLogger(\Exception $exception, $loggerLevel, $throw = true)
    {
        $transitionName  = "t1";
        $subject         = new \StdClass();

        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();
        $workflow->expects(self::once())->method('apply')->with($subject, $transitionName)->willThrowException($exception);
        if ($throw) {
            $this->setExpectedException(get_class($exception));
        }

        $workflowContext = new WorkflowContext($workflow, $subject, "id");

        $logger = $this->getMock(LoggerInterface::class);
        $logger->expects(self::once())->method($loggerLevel);

        $applier = new TransitionApplier(
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $logger
        );

        $applier->applyTransition($workflowContext, $transitionName);
    }

    public function exceptionAndLogLevelProvider()
    {
        return [
            // workflow logic exception should trigger info-level logging
            [new LogicException(), "info", false],
            [new \Exception(), "error"]
        ];
    }

    /**
     * @dataProvider transitionEnvironmentProvider
     */
    public function testSeveralTransitionsHandledCorrectly(array $transitions = [], array $transitionsToBeTriedToApply = [], $cascade = false)
    {
        $subject = new \StdClass();
        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();

        $callIndex = 0;
        $transitionsToApplyCount = 0;
        $workflow->expects(self::at($callIndex))->method('getName');
        $callIndex++;
        foreach ($transitionsToBeTriedToApply as $transitionName => $applicationResult) {
            $invocationMocker = $workflow->expects(self::at($callIndex))->method('apply')->with($subject, $transitionName);
            $transitionsToApplyCount++;
            if (is_array($applicationResult)) {
                $throw = $applicationResult[1];
                $exception = $applicationResult[0];
                $invocationMocker->willThrowException($exception);
                if ($throw) {
                    $this->setExpectedException(get_class($exception));
                    break;
                }
            }
            $callIndex++;
        }
        $workflow->expects($this->exactly($transitionsToApplyCount))->method('apply');

        $workflowContext = new WorkflowContext($workflow, $subject, "id");

        $applier = new TransitionApplier(
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(LoggerInterface::class)
        );

        $applier->applyTransitions($workflowContext, $transitions, $cascade);
    }

    public function transitionEnvironmentProvider()
    {
        return [
            [['t1', 't2'], ['t1' => true]],
            [['t1', 't2'], ['t1' => [new \Exception(), true], 't2' => true], true],
            [['t1', 't2'], ['t1' => [new \Exception(), true], 't2' => true], false],
            [['t1', 't2'], ['t1' => [new LogicException(), false], 't2' => true], true],
            [['t1', 't2'], ['t1' => [new LogicException(), false], 't2' => true], false],
        ];
    }
}
