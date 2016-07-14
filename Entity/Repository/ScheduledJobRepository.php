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

/**
 * ScheduledJobRepository
 */
class ScheduledJobRepository extends EntityRepository
{
    /**
     * Finds ScheduledJob by subject identity and workflow transition
     *
     * @param string $transitionName transition name
     * @param string $workflowName   workflow name
     * @param string $subjectClass   subject class
     * @param string $subjectId      subject id
     *
     * @return ScheduledJob
     */
    public function findScheduledJobForWorkflowTransitionAndSubject($transitionName, $workflowName, $subjectClass, $subjectId)
    {
        return parent::findOneBy([
            'transition'   => $transitionName,
            'workflow'     => $workflowName,
            'subjectClass' => $subjectClass,
            'subjectId'    => $subjectId,
        ]);
    }
}