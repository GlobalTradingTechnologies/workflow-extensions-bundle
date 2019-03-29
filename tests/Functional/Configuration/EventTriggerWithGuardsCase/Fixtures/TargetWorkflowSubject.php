<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 29.06.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\EventTriggerWithGuardsCase\Fixtures;

class TargetWorkflowSubject
{
    private $status;

    public function __construct()
    {
        $this->status = ['inactive' => 1];
    }
    
    public function getStatus() {
        return $this->status;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function getId() {
        return "1";
    }
}
