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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Tests;

use Gtt\Bundle\WorkflowExtensionsBundle\SubjectManipulator;
use Gtt\Bundle\WorkflowExtensionsBundle\TransitionApplier;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class TransitionApplierTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider exceptionAndLogLevelProvider
     */
    public function testUnavailabilityToPerformTransitionIsReportedByLogger(\Exception $exception, $loggerLevel)
    {
        $transitionName = "t1";
        $workflowName = "w1";
        $subject = new \StdClass();

        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();
        $workflow->expects(self::once())->method('apply')->with($subject, $transitionName)->willThrowException($exception);

        $workflowRegistry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $workflowRegistry->expects(self::once())->method('get')->with($subject, $workflowName)->willReturn($workflow);

        $logger = $this->getMock(LoggerInterface::class);
        $logger->expects(self::once())->method($loggerLevel);

        $applier = new TransitionApplier(
            $workflowRegistry,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $logger
        );

        $applier->applyTransition($subject, $workflowName, $transitionName);
    }

    public function exceptionAndLogLevelProvider()
    {
        return [
            // workflow logic exception should trigger info-level logging
            [new LogicException(), "info"],
            [new \Exception(), "error"]
        ];
    }

    /**
     * @dataProvider transitionEnvironmentProvider
     */
    public function testSeveralTransitionsHandledCorrectly(array $transitions = [], array $appliedTransitions = [], $cascade = false)
    {
        $workflowName = "w1";
        $subject = new \StdClass();

        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();

        $appliedTransitionCount = count($appliedTransitions);
        $callIndex = 0;
        foreach ($appliedTransitions as $transitionName => $applicationResult) {
            $invocationMocker = $workflow->expects(self::at($callIndex))->method('apply')->with($subject, $transitionName);
            if ($applicationResult instanceof \Exception) {
                $invocationMocker->willThrowException($applicationResult);
            }
            $callIndex++;
        }
        $workflow->expects($this->exactly($appliedTransitionCount))->method('apply');

        $workflowRegistry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $workflowRegistry->expects(self::once())->method('get')->with($subject, $workflowName)->willReturn($workflow);

        $applier = new TransitionApplier(
            $workflowRegistry,
            $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(LoggerInterface::class)
        );

        $applier->applyTransitions($subject, $workflowName, $transitions, $cascade);
    }

    public function transitionEnvironmentProvider()
    {
        return [
            [['t1', 't2'], ['t1' => true]],
            [['t1', 't2'], ['t1' => new \Exception(), 't2' => true], true],
            [['t1', 't2'], ['t1' => new \Exception(), 't2' => true], false],
            [['t1', 't2'], ['t1' => new LogicException(), 't2' => true], true],
            [['t1', 't2'], ['t1' => new LogicException(), 't2' => true], false],
        ];
    }
}
