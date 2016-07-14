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

namespace Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection;

use DateInterval;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root('gtt_workflow');

        $rootNode
            ->children()
                ->arrayNode('workflows')
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
                ->end()
                ->append($this->addSubjectManipulatorSection())
                ->append($this->addSchedulerSection())
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * Defines triggers config section
     *
     * @return ArrayNodeDefinition
     */
    private function addTriggersSection()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('triggers');

        $node
            ->fixXmlConfig('trigger')
            ->cannotBeEmpty()
            ->children()
                ->arrayNode('event')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('apply')
                                ->cannotBeEmpty()
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) { return array($v); })
                                ->end()
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                            ->arrayNode('schedule')
                                ->cannotBeEmpty()
                                ->defaultValue([])
                                ->requiresAtLeastOneElement()
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('offset')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                            ->info('Holds period defines offset from time of event catching for transition scheduling. '.
                                             'See https://en.wikipedia.org/wiki/ISO_8601#Durations for format description')
                                            ->validate()
                                                ->always()
                                                ->then(function ($v) {
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
                                        ->end()
                                    ->end()
                                ->end()
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
     * Defines guard config section
     *
     * @return ArrayNodeDefinition
     */
    private function addGuardSection()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('guard');

        $node
            ->cannotBeEmpty()
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
                            ->then(function ($v) { return ['expression' => $v]; })
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
    private function addSubjectManipulatorSection()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('subject_manipulator');

        $node
            ->beforeNormalization()
                ->always()
                ->then(
                    function ($v) {
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
    private function addSchedulerSection()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('scheduler');

        $node
            ->cannotBeEmpty()
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
}