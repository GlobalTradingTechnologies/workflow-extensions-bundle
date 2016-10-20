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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action;

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\ActionException;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reference to service method or static class method
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class ActionReference implements ContainerAwareInterface
{
    /**
     * Base default action type
     */
    const TYPE_REGULAR = "regular";

    /**
     * Action type requires WorkflowContext instance as first argument in arguments list
     */
    const TYPE_WORKFLOW = "workflow";

    /**
     * Service id of the object which method is used as an action
     *
     * @var string|null
     */
    private $serviceId;

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
     * @var \ReflectionMethod
     */
    private $reflectionMethod;

    /**
     * Target object object which method is used as an action or null in static context
     * Used internally to invoke action
     *
     * @var object|null
     */
    private $object;

    /**
     * DI Container instance
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * Creates ActionReference by service
     *
     * @param string $methodName method name
     * @param int    $serviceId  service id which method is used as an action
     * @param string $type       action reference type
     */
    public static function createByServiceId($methodName, $serviceId, $type = self::TYPE_REGULAR)
    {
        return new static($methodName, $serviceId, null, $type);
    }

    /**
     * Creates ActionReference by FQCN
     *
     * @param string $methodName method name
     * @param string $class      FQCN of the class which method is used as an action
     * @param string $type       action reference type
     */
    public static function createByClass($methodName, $className, $type = self::TYPE_REGULAR)
    {
        return new static($methodName, null, $className, $type);
    }

    /**
     * ActionReference constructor.
     *
     * @param string $methodName method name
     * @param int    $serviceId  service id which method is used as an action
     * @param string $class      FQCN of the class which method is used as an action
     * @param string $type       action reference type
     */
    private function __construct($methodName, $serviceId = null, $class = null, $type = self::TYPE_REGULAR)
    {
        $this->methodName = $methodName;
        $this->type       = $type;

        if ($serviceId) {
            $this->serviceId = $serviceId;
        }

        if ($class) {
            $this->className = $class;
        }
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
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
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
        return $this->getReflectionMethod()->invokeArgs($this->getObject(), $args);
    }

    /**
     * Returns reflection method
     *
     * @return \ReflectionMethod
     */
    private function getReflectionMethod()
    {
        if (!$this->reflectionMethod) {
            $classOrObject = $this->className ?: $this->getObject();
            $this->reflectionMethod = new \ReflectionMethod($classOrObject, $this->methodName);
        }

        return $this->reflectionMethod;
    }

    /**
     * Returns target object for regular methods and null for static methods
     *
     * @return object|null
     */
    private function getObject()
    {
        if ($this->className) {
            return null;
        }

        if (!$this->object) {
            if (!$this->container) {
                throw ActionException::actionReferenceObjectUnavailable($this->serviceId);
            }

            $this->object = $this->container->get($this->serviceId);
        }

        return $this->object;
    }
}