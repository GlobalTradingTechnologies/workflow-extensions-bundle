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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures;

use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures\ClientBundle\Entity\Client;
use Symfony\Component\EventDispatcher\Event as BaseEvent;

class Event extends BaseEvent
{
    private $subject;
    
    public function __construct(Client $subject)
    {
        $this->subject = $subject;
    }
    
    public function getSubject()
    {
        return $this->subject;
    }
}
