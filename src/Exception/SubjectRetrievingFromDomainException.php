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
 * Subject from domain retrieving faults exception
 */
class SubjectRetrievingFromDomainException extends SubjectManipulatorException
{
    /**
     * Creates exception instance in case of configuration is not found for subject
     *
     * @param string $subjectClass subject class
     *
     * @return static
     */
    public static function expressionNotFound(string $subjectClass): self
    {
        return new static(
            sprintf('Cannot find expression for retrieving subject from domain for class "%s". '.
                'Probably you forget to set value for "subject_from_domain" in bundle configuration',
            $subjectClass)
        );
    }
}
