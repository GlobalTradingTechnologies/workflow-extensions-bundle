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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\ValueObject;

/**
 * Data Value Object represents action name and action arguments
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class Action
{
    /**
     * Name
     *
     * @var string
     */
    private $name;

    /**
     * Arguments
     *
     * @var array
     */
    private $arguments;

    /**
     * Action constructor
     *
     * @param string $name      action name
     * @param array  $arguments action arguments
     */
    public function __construct($name, array $arguments = [])
    {
        $this->name      = $name;
        $this->arguments = $arguments;
    }

    /**
     * Returns action name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns action arguments
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}