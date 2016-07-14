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

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Extension class for DI
 */
class WorkflowExtensionsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($config, $container);
        $config = $this->processConfiguration($configuration, $config);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('applier.xml');

        $this->registerSubjectManipulatorConfiguration($loader, $container, $config['subject_manipulator']);

        foreach ($config['workflows'] as $workflowName => $workflowConfig) {
            if (!empty($workflowConfig['triggers'])) {
                $this->registerTriggerConfiguration(
                    $loader,
                    $container,
                    $workflowConfig['triggers'],
                    $workflowName,
                    !empty($config['scheduler']) ? $config['scheduler'] : []
                );
            }
            if (!empty($workflowConfig['guard'])) {
                $this->registerGuardConfiguration($loader, $container, $workflowConfig['guard'], $workflowName);
            }
        }
    }

    /**
     * Registers subject manipulator config
     *
     * @param LoaderInterface  $loader                   config loader
     * @param ContainerBuilder $container                container
     * @param array            $subjectManipulatorConfig subject manipulator config
     */
    private function registerSubjectManipulatorConfiguration(
        LoaderInterface $loader, ContainerBuilder $container, array $subjectManipulatorConfig)
    {
        $loader->load('subject_manipulator.xml');

        $subjectManipulatorDefinition = $container->findDefinition('gtt.workflow.subject_manipulator');

        $subjectClassesWithSubjectFromDomain = [];
        foreach ($subjectManipulatorConfig as $subjectClass => $subjectConfig) {
            $addSupportedSubjectArgs = [
                $subjectClass,
                $subjectConfig['id_from_subject']
            ];

            if (!empty($subjectConfig['subject_from_domain'])) {
                $subjectClassesWithSubjectFromDomain[] = $subjectClass;
                $addSupportedSubjectArgs[]             = $subjectConfig['subject_from_domain'];
            }

            $subjectManipulatorDefinition->addMethodCall('addSupportedSubject', $addSupportedSubjectArgs);
        }

        // parameter used to check that all the configs needed for scheduling are collected
        // see \Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Compiler\CheckSubjectManipulatorConfigPass
        $container->setParameter('gtt.workflow.subject_classes_with_subject_from_domain', $subjectClassesWithSubjectFromDomain);
    }

    /**
     * Adjusts trigger configurations
     *
     * @param LoaderInterface  $loader          config loader
     * @param ContainerBuilder $container       container
     * @param array            $triggersConfig  triggers config
     * @param string           $workflowName    workflow name
     * @param array            $schedulerConfig scheduler config
     */
    private function registerTriggerConfiguration(
        LoaderInterface $loader,
        ContainerBuilder $container,
        array $triggersConfig,
        $workflowName,
        array $schedulerConfig)
    {
        $loader->load('triggers.xml');

        $workflowsWithScheduling = [];
        $schedulerRegistered = false;
        $triggerEventListenerDefinition = $container->findDefinition('gtt.workflow.trigger.event.listener');
        foreach ($triggersConfig as $triggerType => $triggerConfig) {
            switch ($triggerType) {
                case 'event':
                    foreach ($triggerConfig as $eventName => $eventConfig) {
                        $registerTriggerEventArgs = [
                            $eventName,
                            $workflowName,
                            $eventConfig['subject_retrieving_expression']
                        ];

                        $transitions          = [];
                        $scheduledTransitions = [];

                        if (!empty($eventConfig['apply'])) {
                            $transitions = $eventConfig['apply'];
                        }

                        if (!empty($eventConfig['schedule'])) {
                            if (!$schedulerRegistered) {
                                $this->registerSchedulerConfiguration($loader, $container, $schedulerConfig);
                                $triggerEventListenerDefinition->addMethodCall(
                                    'setScheduler',
                                    [new Reference('gtt.workflow.transition_scheduler')]
                                );
                                $schedulerRegistered = true;
                            }
                            $scheduledTransitions = $eventConfig['schedule'];

                            if (!in_array($workflowName, $workflowsWithScheduling)) {
                                $workflowsWithScheduling[] = $workflowName;
                            }
                        }

                        $registerTriggerEventArgs[] = $transitions;
                        $registerTriggerEventArgs[] = $scheduledTransitions;

                        $triggerEventListenerDefinition->addMethodCall(
                            'registerTriggerEvent',
                            $registerTriggerEventArgs
                        );

                        $triggerEventListenerDefinition->addTag(
                            'kernel.event_listener',
                            ['event' => $eventName, 'method' => 'handleEvent']
                        );
                    }

                    break;
                default:
                    // should never happen
                    throw new InvalidConfigurationException(sprintf("Unknown trigger type %s", $triggerType));
            }
        }

        // parameter used to check that all the configs needed for scheduling are collected
        // see \Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Compiler\CheckSubjectManipulatorConfigPass
        $container->setParameter('gtt.workflow.workflows_with_scheduling', $workflowsWithScheduling);
    }

    /**
     * Adjusts scheduler configurations
     *
     * @param LoaderInterface  $loader          config loader
     * @param ContainerBuilder $container       container
     * @param array            $schedulerConfig scheduler config
     */
    private function registerSchedulerConfiguration(LoaderInterface $loader, ContainerBuilder $container, array $schedulerConfig)
    {
        if (!$schedulerConfig) {
            throw new InvalidConfigurationException('"scheduler" section must be configured');
        }

        $loader->load("scheduler.xml");

        $schedulerDefinition      = $container->findDefinition('gtt.workflow.transition_scheduler');
        $schedulerEntityManagerId = sprintf('doctrine.orm.%s_entity_manager', $schedulerConfig['entity_manager']);
        $schedulerDefinition->replaceArgument(0, new Reference($schedulerEntityManagerId));
    }

    /**
     * Adjusts guard configurations
     *
     * @param LoaderInterface  $loader       config loader
     * @param ContainerBuilder $container    container
     * @param array            $guardConfig  workflow trigger config
     * @param string           $workflowName workflow name
     */
    private function registerGuardConfiguration(LoaderInterface $loader, ContainerBuilder $container, $guardConfig, $workflowName)
    {
        $loader->load('guard.xml');

        $guardDefinition = $container->findDefinition('gtt.workflow.guard');

        if (isset($guardConfig['expression'])) {
            // register workflow-level guard
            $eventName = sprintf('workflow.%s.guard', $workflowName);
            $this->registerGuardListener($guardDefinition, $eventName, $workflowName, $guardConfig['expression']);
        }

        foreach ($guardConfig['transitions'] as $transition => $transitionConfig) {
            // register transition-level guard
            $eventName = sprintf('workflow.%s.guard.%s', $workflowName, $transition);
            $this->registerGuardListener($guardDefinition, $eventName, $workflowName, $transitionConfig['expression']);
        }
    }

    /**
     * Registers expression listener for guard event
     *
     * @param Definition $guardDefinition guard service definition
     * @param string     $eventName       event name
     * @param string     $workflowName    workflow name
     * @param string     $expression      guard expression
     */
    private function registerGuardListener($guardDefinition, $eventName, $workflowName, $expression)
    {
        $guardDefinition->addMethodCall('registerGuardExpression', [$eventName, $workflowName, $expression]);
        $guardDefinition->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'guardTransition']);
    }
}