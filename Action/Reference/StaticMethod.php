<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 14.09.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference;

use ReflectionMethod;

/**
 * Reference to static class method
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class StaticMethod implements ActionReferenceInterface
{
    /**
     * FQCN which static method is used as an action
     *
     * @var string|null
     */
    private $className;

    /**
     * Method name used as action
     *
     * @var string
     */
    private $methodName;

    /**
     * Action type
     *
     * @var string
     */
    private $type;

    /**
     * Action method reflection
     *
     * @var ReflectionMethod
     */
    private $reflectionMethod;

    /**
     * ActionReference constructor.
     *
     * @param string $methodName method name
     * @param string $className  FQCN of the class which method is used as an action
     * @param string $type       action reference type
     */
    public function __construct($methodName, $className, $type = self::TYPE_REGULAR)
    {
        $this->methodName = $methodName;
        $this->className  = $className;
        $this->type       = $type;
    }

    /**
     * Returns action type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Invokes action
     *
     * @param array $args action args
     *
     * @return mixed
     */
    public function invoke(array $args)
    {
        return $this->getReflectionMethod()->invokeArgs(null, $args);
    }

    /**
     * Returns reflection method
     *
     * @return ReflectionMethod
     */
    private function getReflectionMethod()
    {
        if (!$this->reflectionMethod) {
            $this->reflectionMethod = new ReflectionMethod($this->className, $this->methodName);
        }

        return $this->reflectionMethod;
    }
}