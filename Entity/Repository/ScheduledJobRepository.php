<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 01.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\ScheduledJob;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\NonUniqueReschedulabeJobFoundException;
use JMS\JobQueueBundle\Entity\Job;

/**
 * ScheduledJobRepository
 */
class ScheduledJobRepository extends EntityRepository
{
    /**
     * Finds ScheduledJob by subject identity and workflow transition that can be rescheduled (is able to be started now)
     *
     * @param string $workflowName   workflow name
     * @param string $transitionName transition name
     * @param string $subjectClass   subject class
     * @param string $subjectId      subject id
     *
     * @return ScheduledJob|null
     */
    public function findScheduledJobToReschedule($workflowName, $transitionName, $subjectClass, $subjectId)
    {
        // fetching scheduled job that was not started before - it can be rescheduled
        $queryString = <<<'QUERY'
            SELECT sj FROM WorkflowExtensionsBundle:ScheduledJob sj
            JOIN sj.job j
            WHERE
                sj.workflow = :workflow AND
                sj.transition = :transition AND
                sj.subjectClass = :subjectClass AND
                sj.subjectId = :subjectId AND
                j.state in (:stateNew, :statePending)
QUERY;

        /** @var ScheduledJob[] $scheduledJobsToReschedule */
        $scheduledJobsToReschedule = $this->_em
            ->createQuery($queryString)
            ->setParameters([
                'workflow'     => $workflowName,
                'transition'   => $transitionName,
                'subjectClass' => $subjectClass,
                'subjectId'    => $subjectId,
                'stateNew'     => Job::STATE_NEW,
                'statePending' => Job::STATE_PENDING
            ])
            ->getResult()
        ;

        if (!$scheduledJobsToReschedule) {
            return null;
        }

        if (count($scheduledJobsToReschedule) > 1) {
            // since there is normally only one scheduled pending/new job here
            // (because an attempt to schedule duplicate job raises rescheduling of the first one)
            // we throwing exception in case of several results here
            // TODO probably we need support several jobs for the same transition, workflow and subject scheduled for different times?
            $duplicateReschedulableJobsIds = [];
            foreach ($scheduledJobsToReschedule as $scheduledJobToReschedule) {
                $duplicateReschedulableJobsIds[] = $scheduledJobToReschedule->getJob()->getId();
            }

            throw new NonUniqueReschedulabeJobFoundException(
                $workflowName,
                $transitionName,
                $subjectClass,
                $subjectId,
                $duplicateReschedulableJobsIds
            );
        }

        return reset($scheduledJobsToReschedule);
    }
}