<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 02.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Schedule;

use DateInterval;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\InvalidArgumentException;

/**
 * Service value object represents scheduled transition
 */
class ScheduledTransition
{
    /**
     * Transition name to be scheduled
     *
     * @var string
     */
    private $transitionName;

    /**
     * Offset for transition
     *
     * @var string
     */
    private $offset;

    /**
     * ScheduledTransition constructor.
     *
     * @param string $transitionName transition name
     * @param string $offset offset
     *
     * @throws InvalidArgumentException in case of invalid parameters
     */
    public function __construct($transitionName, $offset)
    {
        if (empty($transitionName)) {
            throw new InvalidArgumentException('Scheduled transition name must not be empty');
        }

        $this->transitionName = $transitionName;
        $this->offset = new DateInterval($offset);
    }

    /**
     * Returns transition name
     *
     * @return string
     */
    public function getTransitionName()
    {
        return $this->transitionName;
    }

    /**
     * Returns offset date interval
     *
     * @return DateInterval
     */
    public function getOffset()
    {
        return $this->offset;
    }
}