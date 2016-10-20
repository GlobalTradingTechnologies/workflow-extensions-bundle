<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 01.09.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle;

use Symfony\Component\Workflow\Workflow;

/**
 * Workflow context
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class WorkflowContext
{
    /**
     * Workflow instance
     *
     * @var Workflow
     */
    private $workflow;

    /**
     * Workflow subject instance
     *
     * @var object
     */
    private $subject;

    /**
     * Subject id
     *
     * @var string|int
     */
    private $subjectId;

    /**
     * WorkflowContext constructor.
     *
     * @param Workflow   $workflow
     * @param object     $subject
     * @param string|int $subjectId
     */
    public function __construct(Workflow $workflow, $subject, $subjectId)
    {
        $this->workflow  = $workflow;
        $this->subject   = $subject;
        $this->subjectId = $subjectId;
    }

    /**
     * @return Workflow
     */
    public function getWorkflow()
    {
        return $this->workflow;
    }

    /**
     * @return object
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return int|string
     */
    public function getSubjectId()
    {
        return $this->subjectId;
    }

    /**
     * Returns workflow logger context
     *
     * @return array
     */
    public function getLoggerContext()
    {
        return [
            'workflow' => $this->workflow->getName(),
            'class'    => get_class($this->getSubject()),
            'id'       => $this->subjectId
        ];
    }
}
