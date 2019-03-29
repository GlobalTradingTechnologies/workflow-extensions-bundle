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

/**
 * Subject manipulator faults exception
 */
class SubjectManipulatorException extends \RuntimeException implements WorkflowExceptionInterface
{
    /**
     * Creates exception instance in case of configurations for subject are already set
     *
     * @param string $subjectClass subject class
     *
     * @return static
     */
    public static function subjectConfigIsAlreadySet(string $subjectClass): self
    {
        return new static(sprintf('Subject manipulator config is already set for "%s"', $subjectClass));
    }
}
