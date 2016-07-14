<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 04.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Logger;

/**
 * Provides method to construct logger context with usefull information for workflow components
 */
trait WorkflowLoggerContextTrait
{
    /**
     * Formats workflow logger context
     *
     * @param string $workflowName workflow name
     * @param string $subjectClass subject class
     * @param int    $subjectId    subject id
     *
     * @return array
     */
    private function getLoggerContext($workflowName, $subjectClass, $subjectId)
    {
        return [
            'workflow' => $workflowName,
            'class'    => $subjectClass,
            'id'       => $subjectId
        ];
    }
}