<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 29.07.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject;

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\SubjectIdRetrievingException;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\SubjectManipulatorException;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\SubjectRetrievingFromDomainException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Class helps to retrieve workflow subject from domain and retrieve id from subject used by scheduler subsystem
 */
class SubjectManipulator
{
    /**
     * Expression language
     *
     * @var ExpressionLanguage
     */
    private $language;

    /**
     * Holds expressions used to retrieve subject from domain and id from subject
     *
     * @var array
     */
    private $supportedSubjectsConfig = [];

    /**
     * SubjectManipulator constructor.
     *
     * @param ExpressionLanguage $language expression language
     */
    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    /**
     * Sets expressions for supported subject
     *
     * @param string      $subjectClass                subject class
     * @param string      $idFromSubjectExpression     expression used to retrieve subject id from subject object
     * @param string|null $subjectFromDomainExpression expression used to retrieve subject from domain
     *
     * @throws SubjectManipulatorException
     */
    public function addSupportedSubject(
        string $subjectClass,
        string $idFromSubjectExpression,
        string $subjectFromDomainExpression = null
    ): void {
        if (isset($this->supportedSubjectsConfig[$subjectClass])) {
            throw SubjectManipulatorException::subjectConfigIsAlreadySet($subjectClass);
        }

        $this->supportedSubjectsConfig[$subjectClass] = [
            'id_from_subject'     => $idFromSubjectExpression,
            'subject_from_domain' => $subjectFromDomainExpression
        ];
    }

    /**
     * Retrieves workflow subject from domain
     *
     * @param string $subjectClass subject class
     * @param int    $subjectId    subject id
     *
     * @return object
     *
     * @throws SubjectRetrievingFromDomainException
     */
    public function getSubjectFromDomain(string $subjectClass, $subjectId)
    {
        $subjectClass = ltrim($subjectClass, "\\");
        if (!array_key_exists($subjectClass, $this->supportedSubjectsConfig) ||
            !array_key_exists('subject_from_domain', $this->supportedSubjectsConfig[$subjectClass])) {
            throw SubjectRetrievingFromDomainException::expressionNotFound($subjectClass);
        }

        return $this->language->evaluate(
            $this->supportedSubjectsConfig[$subjectClass]['subject_from_domain'],
            ['subjectClass' => $subjectClass, 'subjectId' => $subjectId]
        );
    }

    /**
     * Fetches subject id from subject object
     *
     * @param object $subject subject
     *
     * @return int
     *
     * @throws SubjectIdRetrievingException
     */
    public function getSubjectId($subject)
    {
        if (!is_object($subject)) {
            throw SubjectIdRetrievingException::subjectIsNotAnObject($subject);
        }

        $subjectClass = get_class($subject);
        if (!array_key_exists($subjectClass, $this->supportedSubjectsConfig) ||
            !array_key_exists('id_from_subject', $this->supportedSubjectsConfig[$subjectClass])) {
            throw SubjectIdRetrievingException::expressionNotFound($subjectClass);
        }

        return $this->language->evaluate(
            $this->supportedSubjectsConfig[$subjectClass]['id_from_subject'],
            ['subject' => $subject]
        );
    }
}
