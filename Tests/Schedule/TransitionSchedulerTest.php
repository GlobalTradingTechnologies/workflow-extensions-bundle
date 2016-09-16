<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date   09.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Tests\Schedule;


use Carbon\Carbon;
use Doctrine\Common\Persistence\ObjectManager;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\Repository\ScheduledJobRepository;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\ScheduledJob;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ScheduledTransition;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\TransitionScheduler;
use Gtt\Bundle\WorkflowExtensionsBundle\SubjectManipulator;
use JMS\JobQueueBundle\Entity\Job;
use Psr\Log\LoggerInterface;

class TransitionSchedulerTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function testInternalExceptionsDoNotBlockExecution()
    {
        list($subject, $subjectClass, $subjectId, $workflowName, $subjectManipulator, $repository, $em) = $this->setupSchedulerContext();

        $transition1 = new ScheduledTransition('t1', 'PT1S');
        $transition2 = new ScheduledTransition('t2', 'PT2S');


        $logger = $this->getMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('error');

        /** @var $repository \PHPUnit_Framework_MockObject_MockObject */
        $repository
            ->expects(self::at(0))
            ->method('findScheduledJobToReschedule')
            ->with($workflowName, $transition1->getTransitionName(), $subjectClass, $subjectId)
            ->willThrowException(new \Exception())
        ;

        $repository
            ->expects(self::at(1))
            ->method('findScheduledJobToReschedule')
            ->with($workflowName, $transition2->getTransitionName(), $subjectClass, $subjectId)
            ->willReturn(null)
        ;
        /** @var $em \PHPUnit_Framework_MockObject_MockObject|ObjectManager */
        $em->expects(self::once())->method('persist')->willThrowException(new \Exception());

        $scheduler = new TransitionScheduler($em, $subjectManipulator, $logger);
        $scheduler->scheduleTransitions($subject, $workflowName, [$transition1, $transition2]);
    }

    public function testSchedulerSchedulesCorrectJob()
    {
        list($subject, $subjectClass, $subjectId, $workflowName, $subjectManipulator, $repository, $em) = $this->setupSchedulerContext();

        $transition = new ScheduledTransition('t1', 'PT1S');

        $transitionTriggerJobArgs = [
            '--transition='   . $transition->getTransitionName(),
            '--workflow='     . $workflowName,
            '--subjectClass=' . $subjectClass,
            '--subjectId='    . $subjectId,
        ];

        Carbon::setTestNow(Carbon::now());
        $now = Carbon::now();
        $executeJobAfter = $now->add($transition->getOffset());

        /** @var $em \PHPUnit_Framework_MockObject_MockObject|ObjectManager */
        $em->expects(self::exactly(2))->method('persist')->withConsecutive(
            [self::callback(
                function (Job $job) use ($transitionTriggerJobArgs, $executeJobAfter) {
                    return
                        $job->getArgs() == $transitionTriggerJobArgs &&
                        $job->getExecuteAfter() == $executeJobAfter;
                }
            )],
            [self::callback(
                function (ScheduledJob $job) use ($subjectClass, $subjectId, $transition, $workflowName) {
                    return
                        $job->getSubjectClass() == $subjectClass &&
                        $job->getSubjectId() == $subjectId &&
                        $job->getTransition() == $transition->getTransitionName() &&
                        $job->getWorkflow() == $workflowName;
                }
            )]
        );

        $scheduler = new TransitionScheduler($em, $subjectManipulator, $this->getMock(LoggerInterface::class));
        $scheduler->scheduleTransitions($subject, $workflowName, [$transition]);
    }

    public function testSchedulerReschedulesCorrectJob()
    {
        list($subject, $subjectClass, $subjectId, $workflowName, $subjectManipulator, $repository, $em) = $this->setupSchedulerContext();

        $transition = new ScheduledTransition('t1', 'PT1S');

        Carbon::setTestNow(Carbon::now());
        $now = Carbon::now();

        $transitionTriggerJob = new Job("command");
        $newExecuteJobAfter = $now->add($transition->getOffset());

        $scheduledJob = new ScheduledJob($workflowName, $transition->getTransitionName(), $subjectClass, $subjectId, $transitionTriggerJob);

        $repository
            ->expects(self::once())
            ->method('findScheduledJobToReschedule')
            ->with($workflowName, $transition->getTransitionName(), $subjectClass, $subjectId)
            ->willReturn($scheduledJob)
        ;

        /** @var $em \PHPUnit_Framework_MockObject_MockObject|ObjectManager */
        $em->expects(self::once())->method('persist')->withConsecutive(
            [self::callback(
                function (Job $job) use ($newExecuteJobAfter) {
                    return $job->getExecuteAfter() == $newExecuteJobAfter;
                }
            )]
        );

        $scheduler = new TransitionScheduler($em, $subjectManipulator, $this->getMock(LoggerInterface::class));
        $scheduler->scheduleTransitions($subject, $workflowName, [$transition]);
    }

    /**
     * @return array
     */
    private function setupSchedulerContext()
    {
        $subject = new \StdClass();
        $subjectClass = get_class($subject);
        $subjectId = '1';

        $workflowName = 'w1';

        $subjectManipulator = $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock();
        $subjectManipulator->expects(self::once())->method('getSubjectId')->with($subject)->willReturn($subjectId);

        $repository = $this->getMockBuilder(ScheduledJobRepository::class)->disableOriginalConstructor()->getMock();

        $em = $this->getMockBuilder(ObjectManager::class)->disableOriginalConstructor()->getMock();
        $em->expects(self::once())->method('getRepository')->with(ScheduledJob::class)->willReturn($repository);

        return array($subject, $subjectClass, $subjectId, $workflowName, $subjectManipulator, $repository, $em);
    }
}
