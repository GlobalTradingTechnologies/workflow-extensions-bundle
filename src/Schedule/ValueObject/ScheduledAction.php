<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 20.09.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ValueObject;

use DateInterval;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\ValueObject\Action;

/**
 * Data Value Object represents scheduled action
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class ScheduledAction extends Action
{
    /**
     * Offset for transition
     *
     * @var DateInterval
     */
    private $offset;

    /**
     * Flag defines current scheduled action can be rescheduled or not
     *
     * @var boolean
     */
    private $isReschedulable;

    public function __construct(string $name, array $arguments, string $offset, bool $isReschedulable = false)
    {
        parent::__construct($name, $arguments);
        $this->offset          = new DateInterval($offset);
        $this->isReschedulable = $isReschedulable;
    }

    /**
     * Returns offset date interval
     *
     * @return DateInterval
     */
    public function getOffset(): DateInterval
    {
        return $this->offset;
    }

    /**
     * @return boolean
     */
    public function isReschedulable(): bool
    {
        return $this->isReschedulable;
    }
}
