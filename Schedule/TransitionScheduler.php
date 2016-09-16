<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 28.07.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Schedule;

use Carbon\Carbon;
use Doctrine\Common\Persistence\ObjectManager;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\Repository\ScheduledJobRepository;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\ScheduledJob;
use Gtt\Bundle\WorkflowExtensionsBundle\Logger\WorkflowLoggerContextTrait;
use Gtt\Bundle\WorkflowExtensionsBundle\SubjectManipulator;
use JMS\JobQueueBundle\Entity\Job;
use Psr\Log\LoggerInterface;

/**
 * Schedules transition to apply after some time
 */
class TransitionScheduler
{
    use WorkflowLoggerContextTrait;

    /**
     * Persistance object manager
     *
     * @var ObjectManager
     */
    private $em;

    /**
     * Subject manipulator
     *
     * @var SubjectManipulator
     */
    private $subjectManipulator;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * TransitionScheduler constructor.
     *
     * @param ObjectManager      $em                 entity manager
     * @param SubjectManipulator $subjectManipulator subject manipulator
     * @param LoggerInterface    $logger             logger
     */
    public function __construct(ObjectManager $em, SubjectManipulator $subjectManipulator, LoggerInterface $logger)
    {
        $this->em                 = $em;
        $this->subjectManipulator = $subjectManipulator;
        $this->logger             = $logger;
    }

    /**
     * Schedules transitions
     *
     * @param object                $subject              current subject
     * @param string                $workflowName         workflow name
     * @param ScheduledTransition[] $scheduledTransitions list of scheduled transitions
     */
    public function scheduleTransitions($subject, $workflowName, $scheduledTransitions)
    {
        $subjectClass = get_class($subject);
        $subjectId    = $this->subjectManipulator->getSubjectId($subject);

        $loggerContext = $this->getLoggerContext($workflowName, $subjectClass, $subjectId);

        /** @var ScheduledJobRepository $scheduledJobRepository */
        $scheduledJobRepository = $this->em->getRepository(ScheduledJob::class);

        foreach ($scheduledTransitions as $scheduledTransition) {
            $transitionName = $scheduledTransition->getTransitionName();
            try {
                $scheduledJob = $scheduledJobRepository->findScheduledJobToReschedule(
                    $workflowName, $scheduledTransition->getTransitionName(), $subjectClass, $subjectId
                );
                if ($scheduledJob) {
                    // the job was already scheduled but not executed. Now we need to reschedule it
                    $this->rescheduleTransitionTriggerJob($scheduledTransition, $scheduledJob, $loggerContext);
                } else {
                    // creating new jms job to trigger transition
                    $this->scheduleTransitionTriggerJob($scheduledTransition, $workflowName, $subjectClass, $subjectId, $loggerContext);
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf('Workflow cannot schedule/reschedule transition "%s". Details: %s', $transitionName, $e->getMessage()),
                    $loggerContext
                );
            } catch (\Throwable $e) {
                $this->logger->error(
                    sprintf('Workflow cannot schedule/reschedule transition "%s". Details: %s', $transitionName, $e->getMessage()),
                    $loggerContext
                );
            }
        }
    }

    /**
     * Reschedules already scheduled transition
     *
     * @param ScheduledTransition $scheduledTransition scheduled transition
     * @param ScheduledJob        $scheduledJob        scheduled job for transition
     * @param array               $loggerContext       logger context
     */
    private function rescheduleTransitionTriggerJob(ScheduledTransition $scheduledTransition, ScheduledJob $scheduledJob, array $loggerContext)
    {
        $transitionTriggerJob = $scheduledJob->getJob();
        $transitionTriggerJob->setExecuteAfter($this->getTransitionTriggerExecutionDate($scheduledTransition));

        // since jms Job states DEFERRED_EXPLICIT change tracking policy we should explicitly persist entity now
        $this->em->persist($transitionTriggerJob);
        $this->em->flush();

        $this->logger->info(
            sprintf("Workflow successfully rescheduled transition '%s'", $scheduledTransition->getTransitionName()),
            $loggerContext
        );
    }

    /**
     * Schedules transition
     *
     * @param ScheduledTransition $scheduledTransition scheduled transition
     * @param string              $workflowName        workflow name
     * @param string              $subjectClass        subject class
     * @param int                 $subjectId           subject id
     * @param array               $loggerContext       logger context
     */
    private function scheduleTransitionTriggerJob($scheduledTransition, $workflowName, $subjectClass, $subjectId, $loggerContext)
    {
        $transitionTriggerJob = new Job('workflow:transition:trigger',
            [
                '--transition='   . $scheduledTransition->getTransitionName(),
                '--workflow='     . $workflowName,
                '--subjectClass=' . $subjectClass,
                '--subjectId='    . $subjectId,
            ]
        );

        $executionDate = $this->getTransitionTriggerExecutionDate($scheduledTransition);
        $transitionTriggerJob->setExecuteAfter($executionDate);

        $scheduledJob = new ScheduledJob(
            $workflowName,
            $scheduledTransition->getTransitionName(),
            $subjectClass,
            $subjectId,
            $transitionTriggerJob
        );

        $this->em->persist($transitionTriggerJob);
        $this->em->persist($scheduledJob);

        $this->em->flush();

        $this->logger->info(
            sprintf("Workflow successfully scheduled transition '%s'", $scheduledTransition->getTransitionName()),
            $loggerContext
        );
    }

    /**
     * Calculates execution date date for scheduled transition
     *
     * @param ScheduledTransition $scheduledTransition scheduled transition
     *
     * @return \DateTime
     */
    private function getTransitionTriggerExecutionDate(ScheduledTransition $scheduledTransition)
    {
        $executionDate = Carbon::now();
        $executionDate->add($scheduledTransition->getOffset());

        return $executionDate;
    }
}