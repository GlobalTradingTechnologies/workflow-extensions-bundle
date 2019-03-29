<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 19.07.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\ExpressionLanguage;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Extends DI Expression Language with container variable holds ContainerInterface implementation
 */
class ContainerAwareExpressionLanguage extends ExpressionLanguage
{
    /**
     * DI ContainerInterface implementation
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * ContainerAwareExpressionLanguage constructor
     *
     * @param ContainerInterface                    $container DI Container
     * @param CacheItemPoolInterface                $cache     cache
     * @param ExpressionFunctionProviderInterface[] $providers providers list
     */
    public function __construct(ContainerInterface $container, CacheItemPoolInterface $cache = null, array $providers = array())
    {
        $this->container = $container;
        parent::__construct($cache, $providers);
    }

    /**
     * Compile an expression with ContainerInterface context
     *
     * {@inheritdoc}
     */
    public function compile($expression, $names = array())
    {
        return parent::compile($expression, array_unique(array_merge($names, ["container"])));
    }

    /**
     * Evaluate an expression with ContainerInterface context
     *
     * {@inheritdoc}
     */
    public function evaluate($expression, $values = array())
    {
        return parent::evaluate($expression, $values + ["container" => $this->container]);
    }

    /**
     * Parse an expression with ContainerInterface context
     *
     * {@inheritdoc}
     */
    public function parse($expression, $names)
    {
        return parent::parse($expression, array_unique(array_merge($names, ["container"])));
    }
}
