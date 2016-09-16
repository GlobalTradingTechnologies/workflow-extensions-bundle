<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 03.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Exception;

use Exception;
use JMS\JobQueueBundle\Entity\Job;

/**
 * Exception for cases whe several reschedulable jobs for workflow transition and subject found
 */
class NonUniqueReschedulabeJobFoundException extends \RuntimeException implements WorkflowExceptionInterface
{
    /**
     * NonUniqueReschedulabeJobFoundException constructor
     *
     * @param string         $workflowName   workflow name
     * @param string         $transitionName transition name
     * @param string         $subjectClass   subject class
     * @param string         $subjectId      subject id
     * @param array          $jobIds         list of ids of reschedulable jms jobs
     * @param int            $code           exception code
     * @param Exception|null $previous       previous exception
     */
    public function __construct($workflowName, $transitionName, $subjectClass, $subjectId, array $jobIds, $code = 0, Exception $previous = null)
    {
        $message = sprintf(
            "There are several scheduled '%s' instances (id's: '%s') available for rescheduling found for transition '%s' (workflow '%s') and ".
            "subject class '%s' and subject id '%s'",
            Job::class,
            implode(", ", $jobIds),
            $transitionName,
            $workflowName,
            $subjectClass,
            $subjectId
        );
        parent::__construct($message, $code, $previous);
    }
}