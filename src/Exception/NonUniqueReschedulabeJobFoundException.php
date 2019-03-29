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
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Exception;

use JMS\JobQueueBundle\Entity\Job;
use Throwable;

/**
 * Exception for cases whe several reschedulable jobs for workflow transition and subject found
 */
class NonUniqueReschedulabeJobFoundException extends \RuntimeException implements WorkflowExceptionInterface
{
    /**
     * NonUniqueReschedulabeJobFoundException constructor
     *
     * @param Job            $originalJob duplicated job
     * @param array          $jobIds      list of ids of reschedulable jms jobs
     * @param int            $code        exception code
     * @param Throwable|null $previous    previous exception
     */
    public function __construct(Job $originalJob, array $jobIds, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            "There are several scheduled '%s' jobs (id's: '%s') available for rescheduling found for command '%s' with args '%s'",
            Job::class,
            implode(", ", $jobIds),
            $originalJob->getCommand(),
            json_encode($originalJob->getArgs())
        );
        parent::__construct($message, $code, $previous);
    }
}
