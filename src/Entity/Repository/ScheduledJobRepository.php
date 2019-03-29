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
declare(strict_types=1);

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
     * Finds ScheduledJob by original Job can be scheduled earlier
     *
     * @param $originalJob $originalJob original job
     *
     * @return ScheduledJob|null
     */
    public function findScheduledJobToReschedule(Job $originalJob)
    {
        // fetching scheduled job that was not started before - it can be rescheduled
        $queryString = <<<'QUERY'
            SELECT sj FROM WorkflowExtensionsBundle:ScheduledJob sj
            JOIN sj.job j
            WHERE
                j.state in (:stateNew, :statePending) AND
                j.command = :command AND
                j.args = :args AND
                sj.reschedulable = 1
QUERY;

        /** @var ScheduledJob[] $scheduledJobsToReschedule */
        $scheduledJobsToReschedule = $this->_em
            ->createQuery($queryString)
            ->setParameters([
                'stateNew'     => Job::STATE_NEW,
                'statePending' => Job::STATE_PENDING,
                'command'      => $originalJob->getCommand(),
                'args'         => json_encode($originalJob->getArgs())
            ])
            ->getResult()
        ;

        if (!$scheduledJobsToReschedule) {
            return null;
        }

        if (\count($scheduledJobsToReschedule) > 1) {
            // since there is normally only one scheduled pending/new job here
            // (because an attempt to schedule duplicate job raises rescheduling of the first one)
            // we throwing exception in case of several results here
            // TODO probably we need support several jobs for the same transition, workflow and subject scheduled for different times?
            $duplicateReschedulableJobsIds = [];
            foreach ($scheduledJobsToReschedule as $scheduledJobToReschedule) {
                $duplicateReschedulableJobsIds[] = $scheduledJobToReschedule->getJob()->getId();
            }

            throw new NonUniqueReschedulabeJobFoundException($originalJob, $duplicateReschedulableJobsIds);
        }

        return reset($scheduledJobsToReschedule);
    }
}
