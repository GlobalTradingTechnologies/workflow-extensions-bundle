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
    public function testUnavailabilityToPerformTransitionIsReportedByLogger(\Exception $exception, $loggerLevel)
    {
        $transitionName  = "t1";
        $subject         = new \StdClass();

        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();
        $workflow->expects(self::once())->method('apply')->with($subject, $transitionName)->willThrowException($exception);

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
        $workflow->expects(self::at($callIndex))->method('getName');
        $callIndex++;
        foreach ($appliedTransitions as $transitionName => $applicationResult) {
            $invocationMocker = $workflow->expects(self::at($callIndex))->method('apply')->with($subject, $transitionName);
            if ($applicationResult instanceof \Exception) {
                $invocationMocker->willThrowException($applicationResult);
            }
            $callIndex++;
        }
        $workflow->expects($this->exactly($appliedTransitionCount))->method('apply');

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
            [['t1', 't2'], ['t1' => new \Exception(), 't2' => true], true],
            [['t1', 't2'], ['t1' => new \Exception(), 't2' => true], false],
            [['t1', 't2'], ['t1' => new LogicException(), 't2' => true], true],
            [['t1', 't2'], ['t1' => new LogicException(), 't2' => true], false],
        ];
    }
}
