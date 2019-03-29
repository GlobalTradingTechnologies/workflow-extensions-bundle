<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 17.07.15
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection;

use DateInterval;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ActionReferenceInterface;
use Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Enum\ActionArgumentTypes;
use Gtt\Bundle\WorkflowExtensionsBundle\Utils\ArrayUtils;
use ReflectionClass;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * Configuration class for DI
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('workflow_extensions');
        $rootNode    = $treeBuilder->root('workflow_extensions');

        $rootNode
            ->children()
                ->append($this->addActionsSection())
                ->append($this->addWorkflowsSection())
                ->append($this->addSubjectManipulatorSection())
                ->append($this->addSchedulerSection())
                ->append($this->addContextSection())
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * Defines config section defines list of application actions
     *
     * @return ArrayNodeDefinition
     */
    private function addActionsSection(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('actions');

        $node
            ->useAttributeAsKey('name')
            ->cannotBeEmpty()
            ->requiresAtLeastOneElement()
            ->prototype('array')
                ->children()
                    ->enumNode('type')
                        ->values([ActionReferenceInterface::TYPE_REGULAR, ActionReferenceInterface::TYPE_WORKFLOW])
                        ->cannotBeEmpty()
                        ->defaultValue(ActionReferenceInterface::TYPE_REGULAR)
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static function($v) {
                                return constant(ActionReferenceInterface::class."::TYPE_".strtoupper($v));
                            })
                        ->end()
                    ->end()
                    ->scalarNode('service')
                        ->cannotBeEmpty()
                        ->info('Service id of the class contains action method')
                    ->end()
                    ->scalarNode('class')
                        ->cannotBeEmpty()
                        ->info('Class contains action method')
                    ->end()
                    ->scalarNode('method')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('Action method of the service')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->always()
                ->then(static function ($v) {
                    foreach ($v as $actionName => $actionConfig) {
                        if (!preg_match('/^[a-z0-9_]+$/i', $actionName)) {
                            throw new InvalidConfigurationException(
                                sprintf(
                                    'Action name must contain only alphanumeric and underscore symbols. Please rename action "%s"',
                                    $actionName
                                )
                            );
                        }
                        if (!(array_key_exists('class', $actionConfig) xor array_key_exists('service', $actionConfig))) {
                            throw new InvalidConfigurationException(
                                sprintf(
                                    'Please change configuration for "%s" action. It is possible to set "class" or "service" option, but not the both',
                                    $actionName
                                )
                            );
                        }
                        if (array_key_exists('class', $actionConfig)) {
                            if (!class_exists($actionConfig['class'])) {
                                throw new InvalidConfigurationException(
                                    sprintf(
                                        'The class "%s" configured for "%s" action does not exist',
                                        $actionConfig['class'],
                                        $actionName
                                    )
                                );
                            }
                            if (!method_exists($actionConfig['class'], $actionConfig['method'])) {
                                throw new InvalidConfigurationException(
                                    sprintf(
                                        'The class "%s" configured for "%s" action does not have "%s" method',
                                        $actionConfig['class'],
                                        $actionName,
                                        $actionConfig['method']
                                    )
                                );
                            }
                        }
                    }

                    return $v;
                })
            ->end()
        ;

        return $node;
    }

    /**
     * Defines workflows section
     *
     * @return ArrayNodeDefinition
     */
    private function addWorkflowsSection(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('workflows');

        $node
            ->fixXmlConfig('workflow')
            ->isRequired()
            ->cannotBeEmpty()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->children()
                    ->append($this->addTriggersSection())
                    ->append($this->addGuardSection())
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Defines triggers config section
     *
     * @return ArrayNodeDefinition
     */
    private function addTriggersSection(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('triggers');

        $node
            ->fixXmlConfig('trigger')
            ->children()
                ->arrayNode('event')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('actions')
                                ->cannotBeEmpty()
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(static function ($v) { return [$v]; })
                                ->end()
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->prototype('array')
                                        ->children()
                                            ->append($this->buildActionArgumentsNode())
                                        ->end()
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(static function ($v) {
                                                return ['arguments' => [$v]];
                                            })
                                        ->end()
                                        ->beforeNormalization()
                                            ->ifTrue(static function ($v) {
                                                // place arguments under 'arguments' key in order to validate it in common way
                                                return \is_array($v) && !(\count($v) === 1 && \array_key_exists('arguments', $v));
                                            })
                                            ->then(static function ($v) {
                                                return ['arguments' => $v];
                                            })
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('schedule')
                                ->cannotBeEmpty()
                                ->defaultValue([])
                                ->requiresAtLeastOneElement()
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->prototype('array')
                                        ->children()
                                            ->append($this->buildActionArgumentsNode())
                                            ->append($this->addOffsetSection())
                                            ->booleanNode('reschedulable')
                                                ->defaultTrue()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('expression')
                                ->info('Expression to execute as a event reaction')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('subject_retrieving_expression')
                                ->info(
                                    'Expression should return subject object for workflow processing '.
                                    'Context variables: event')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Build prototyped recursive list of arguments of an action
     *
     * @param NodeDefinition|null $targetArgumentsNode parent node. If not set it will be created with the 'arguments' name
     *
     * @return ArrayNodeDefinition
     */
    private function buildActionArgumentsNode(NodeDefinition $targetArgumentsNode = null): ArrayNodeDefinition
    {
        if (!$targetArgumentsNode) {
            $targetArgumentsNode = new ArrayNodeDefinition('arguments');
        }

        $targetArgumentsNode
            ->defaultValue([])
            ->prototype('array')
                ->children()
                    ->enumNode('type')
                        ->values($this->getSupportedActionArgumentTypes())
                        ->cannotBeEmpty()
                        ->defaultValue(ActionArgumentTypes::TYPE_SCALAR)
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static function($v) {
                                return constant(ActionArgumentTypes::class.'::TYPE_'.strtoupper($v));
                            })
                        ->end()
                    ->end()
                    ->variableNode('value')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                ->beforeNormalization()
                    ->ifTrue(static function ($v) {
                        return \is_scalar($v) || (\is_array($v) && !ArrayUtils::isArrayAssoc($v));
                    })
                    ->then(static function ($v) {
                        $type = \is_scalar($v) ? ActionArgumentTypes::TYPE_SCALAR : ActionArgumentTypes::TYPE_ARRAY;
                        return [
                            'type'  => $type,
                            'value' => $v
                        ];
                    })
                ->end()
                ->validate()
                    ->ifTrue(static function ($v) {
                        return \is_array($v) && $v['type'] === ActionArgumentTypes::TYPE_ARRAY;
                    })
                    ->then(function ($v) {
                        // recursive processing of array values
                        $node = new ArrayNodeDefinition('value');
                        $this->buildActionArgumentsNode($node);
                        $processor = new Processor();
                        $config = $processor->process($node->getNode(true), [$v['value']]);
                        $v['value'] = $config;

                        return $v;
                    })
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static function ($v): bool {
                    return ArrayUtils::isArrayAssoc($v, false);
                })
                // To provide cross-platform arguments handling we are not supporting here assoc arrays
                ->thenInvalid('Only non-associate list of arguments are supported')
            ->end()
        ;

        return $targetArgumentsNode;
    }

    /**
     * Returns allowed action argument types
     *
     * @see ActionArgumentTypes
     *
     * @return array
     */
    private function getSupportedActionArgumentTypes(): array
    {
        static $supportedTypes = null;

        if ($supportedTypes === null) {
            $actionArgumentTypesRef = new ReflectionClass(ActionArgumentTypes::class);
            $supportedTypes         = $actionArgumentTypesRef->getConstants();
        }

        return $supportedTypes;
    }

    /**
     * Defines scheduler offset config session
     *
     * @return NodeDefinition
     */
    private function addOffsetSection(): NodeDefinition
    {
        $node = new ScalarNodeDefinition('offset');

        return $node
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Holds period defines offset from time of event catching for transition scheduling. '.
             'See https://en.wikipedia.org/wiki/ISO_8601#Durations for format description')
            ->validate()
                ->always()
                ->then(static function ($v) {
                    try {
                        new DateInterval($v);

                        return $v;
                    } catch (\Exception $e) {
                        throw new InvalidConfigurationException(
                            sprintf('Scheduled transition offset value %s is not valid. '.
                                'Please use ISO 8601 duration spec. Details: %s',
                                $v,
                                $e->getMessage()
                            )
                        );
                    }
                })
            ->end()
        ;
    }

    /**
     * Defines guard config section
     *
     * @return ArrayNodeDefinition
     */
    private function addGuardSection(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('guard');

        $node
            ->children()
                ->scalarNode('expression')
                    ->info('Workflow-level expression that can block or allow transitions. Result should be boolean')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('transitions')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static function (string $v): array { return ['expression' => $v]; })
                        ->end()
                        ->children()
                            ->scalarNode('expression')
                                ->info('Transition-level expression that can block or allow transition. Result should be boolean')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Defines subject manipulator config section
     *
     * @return ArrayNodeDefinition
     */
    private function addSubjectManipulatorSection(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('subject_manipulator');

        $node
            ->beforeNormalization()
                ->always()
                ->then(
                    static function ($v) {
                        if (isset($v) && is_array($v)) {
                            foreach ($v as $key => $value) {
                                unset($v[$key]);
                                $key = ltrim($key, "\\");
                                if (!class_exists($key)) {
                                    throw new InvalidConfigurationException(
                                        sprintf('Subject class "%s" does not exists', $key)
                                    );
                                }
                                $v[ltrim($key, "\\")] = $value;
                            }
                        }
                        return $v;
                    }
                )
            ->end()
            ->isRequired()
            ->cannotBeEmpty()
            ->useAttributeAsKey('name')
            ->requiresAtLeastOneElement()
            ->prototype('array')
                ->children()
                    ->scalarNode('subject_from_domain')
                        ->cannotBeEmpty()
                        ->info('Expression used to retrieve workflow subject from event triggers workflow actions. '.
                            'Context variables: subjectId, subjectClass'
                        )
                    ->end()
                    ->scalarNode('id_from_subject')
                        ->defaultValue('subject.getId()')
                        ->cannotBeEmpty()
                        ->info('Expression used to retrieve subject identifier from subject object. '.
                            'Identifier can be used later to schedule or reschedule transitions. '.
                            'Context variables: subject'
                        )
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Defines scheduler config section
     *
     * @return ArrayNodeDefinition
     */
    private function addSchedulerSection(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('scheduler');

        $node
            ->children()
                ->scalarNode('entity_manager')
                    ->info('Holds entity manager name to persist scheduler jobs')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * Defines services available inside of this bundle container
     *
     * @return ArrayNodeDefinition
     */
    private function addContextSection(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('context');
        $node
            ->defaultValue([])
            ->treatNullLike([])
            ->scalarPrototype()
            ->end()
            ->info('Array of services available inside the container used by workflow engine')
            ->example(['doctrine' => null, 'auth' => 'security.authorization_checker'])
            ->beforeNormalization()
                ->ifString()
                ->then(static function (string $item): array {
                    return [$item => $item];
                })
            ->end()
            ->validate()
                ->ifArray()
                ->then(static function (array $config): array {
                    foreach ($config as $k => $v) {
                        if ($v === null) {
                            $config[$k] = $k;
                        }
                    }

                    return $config;
                })
            ->end();

        return $node;
    }
}
