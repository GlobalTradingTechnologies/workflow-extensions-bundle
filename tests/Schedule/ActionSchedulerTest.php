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
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Schedule;

use Carbon\Carbon;
use Doctrine\Common\Persistence\ObjectManager;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\Repository\ScheduledJobRepository;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\ScheduledJob;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ValueObject\ScheduledAction;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use JMS\JobQueueBundle\Entity\Job;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Workflow;

class ActionSchedulerTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    /**
     * @dataProvider scheduledActionProvider
     */
    public function testSchedulerSchedulesCorrectJob(ScheduledAction $action, bool $alreadyScheduled): void
    {
        /** @var WorkflowContext $workflowContext */
        [$workflowContext, $repository, $em] = $this->setupSchedulerContext();

        Carbon::setTestNow(Carbon::now());
        $now             = Carbon::now();

        $executeJobAfter = clone $now;
        $executeJobAfter->add($action->getOffset());

        if ($action->isReschedulable()) {
            if ($alreadyScheduled) {
                // expecting rescheduling
                $actionJob    = new Job('command', ['--some' => 'args']);
                $scheduledJob = new ScheduledJob($actionJob);

                $expectedActionJob = clone $actionJob;
                $expectedActionJob->setExecuteAfter($executeJobAfter);

                $repository->expects(self::once())->method('findScheduledJobToReschedule')->willReturn($scheduledJob);
                $em->expects(self::once())->method('persist')->with(self::callback(static function (Job $actual) use ($expectedActionJob) {
                    self::assertEquals($expectedActionJob->getExecuteAfter(), $actual->getExecuteAfter());

                    return true;
                }));
            } else {
                // expecting the new one
                $repository->expects(self::once())->method('findScheduledJobToReschedule')->willReturn(null);
                $this->configureEmToExpectNewJobCreation($em, $action, $workflowContext, $executeJobAfter);
            }
        } else {
            // non-reschedulable action - expecting creation of the new job
            $repository->expects(self::never())->method('findScheduledJobToReschedule')->willReturn(null);
            $this->configureEmToExpectNewJobCreation($em, $action, $workflowContext, $executeJobAfter);
        }

        $scheduler = new ActionScheduler($em, $this->getMockBuilder(LoggerInterface::class)->getMockForAbstractClass());
        $scheduler->scheduleAction($workflowContext, $action);
    }

    public function scheduledActionProvider(): array
    {
        return [
            [new ScheduledAction('a1', [], 'PT1S'), true],
            [new ScheduledAction('a1', [], 'PT1S'), false],
            [new ScheduledAction('a1', [], 'PT1S', true), true],
            [new ScheduledAction('a1', [], 'PT1S', true), false]
        ];
    }

    private function configureEmToExpectNewJobCreation(ObjectManager $em, ScheduledAction $action, WorkflowContext $workflowContext, \DateTime $executeJobAfter): void
    {
        $expectedActionJob = new Job(
            'workflow:action:execute',
            [
                '--action='       . $action->getName(),
                '--arguments='    . json_encode($action->getArguments()),
                '--workflow='     . $workflowContext->getWorkflow()->getName(),
                '--subjectClass=' . get_class($workflowContext->getSubject()),
                '--subjectId='    . $workflowContext->getSubjectId()
            ]
        );

        $expectedActionJob->setExecuteAfter($executeJobAfter);

        /** @var $em MockObject|ObjectManager */
        $em->expects(self::exactly(2))->method('persist')->withConsecutive(
            self::equalTo($expectedActionJob),
            self::equalTo(new ScheduledJob($expectedActionJob, $action->isReschedulable()))
        );
    }

    /**
     * @return array
     */
    private function setupSchedulerContext(): array
    {
        $subject = new \StdClass();
        $subjectId = '1';

        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();
        $workflow->expects(self::any())->method('getName')->willReturn('w1');

        $workflowContext = new WorkflowContext($workflow, $subject, $subjectId);

        $repository = $this->getMockBuilder(ScheduledJobRepository::class)->disableOriginalConstructor()->getMock();

        $em = $this->getMockBuilder(ObjectManager::class)->disableOriginalConstructor()->getMock();
        $em->expects(self::once())->method('getRepository')->with(ScheduledJob::class)->willReturn($repository);

        return [$workflowContext, $repository, $em];
    }
}
