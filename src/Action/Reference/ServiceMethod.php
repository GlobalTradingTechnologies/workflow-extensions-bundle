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

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\ActionException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reference to service method
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class ServiceMethod implements ActionReferenceInterface, ContainerAwareInterface
{
    /**
     * Service id of the object which method is used as an action
     *
     * @var string
     */
    private $serviceId;

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
     * Target object object which method is used as an action
     * Used internally to invoke action
     *
     * @var object
     */
    private $object;

    /**
     * DI Container instance
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * ActionReference constructor.
     *
     * @param string $methodName method name
     * @param int    $serviceId  service id which method is used as an action
     * @param string $type       action reference type
     */
    public function __construct($methodName, $serviceId, $type = self::TYPE_REGULAR)
    {
        $this->methodName = $methodName;
        $this->serviceId  = $serviceId;
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
     * @return ReflectionMethod
     */
    private function getReflectionMethod()
    {
        if (!$this->reflectionMethod) {
            $this->reflectionMethod = new ReflectionMethod($this->getObject(), $this->methodName);
        }

        return $this->reflectionMethod;
    }

    /**
     * Returns service object
     *
     * @return object
     */
    private function getObject()
    {
        if (!$this->object) {
            if (!$this->container) {
                throw ActionException::containerUnavailableForServiceMethodReference($this->serviceId);
            }

            $this->object = $this->container->get($this->serviceId);
        }

        return $this->object;
    }
}
