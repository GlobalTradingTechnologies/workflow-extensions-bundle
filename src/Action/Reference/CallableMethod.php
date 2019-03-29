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
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference;

/**
 * Reference to some callable
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class CallableMethod implements ActionReferenceInterface
{
    /**
     * Action type
     *
     * @var string
     */
    private $type;

    /**
     * Callable which will be used as an action
     *
     * @var callable
     */
    private $method;

    /**
     * ActionReference constructor.
     *
     * @param callable $method Callable which will be used as an action
     * @param string   $type   action reference type
     */
    public function __construct(callable $method, string $type = self::TYPE_REGULAR)
    {
        $this->type   = $type;
        $this->method = $method;
    }

    /**
     * Returns action type
     *
     * @return string
     */
    public function getType(): string
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
        $callable = $this->method;

        return $callable(...$args);
    }
}
