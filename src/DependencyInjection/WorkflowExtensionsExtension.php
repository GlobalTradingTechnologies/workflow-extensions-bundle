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

use Gtt\Bundle\WorkflowExtensionsBundle\Guard\ExpressionGuard;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
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

        $loader->load('actions.xml');

        $this->registerSubjectManipulatorConfiguration($loader, $container, $config['subject_manipulator']);
        $this->registerContextConfiguration($container, $config['context']);

        if (array_key_exists('workflows', $config)) {
            $this->registerWorkflowsConfiguration($loader, $container, $config['workflows']);
        }

        // if there are triggers with actions scheduling, register scheduler config
        if ($container->getParameter('gtt.workflow.workflows_with_scheduling')) {
            $this->registerSchedulerConfiguration($loader, $container, $config['scheduler']);
        } else {
            // remove scheduler listener if scheduler is not used
            $container->removeDefinition('gtt.workflow.trigger.event.listener.scheduler');
        }

        if (array_key_exists('actions', $config)) {
            $this->registerActionsConfiguration($config['actions'], $container);
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
        LoaderInterface $loader,
        ContainerBuilder $container,
        array $subjectManipulatorConfig
    ): void {
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
     * Registers workflows configuration section
     *
     * @param LoaderInterface  $loader          config loader
     * @param ContainerBuilder $container       container
     * @param array            $workflowsConfig workflows config
     */
    private function registerWorkflowsConfiguration(
        LoaderInterface $loader,
        ContainerBuilder $container,
        array $workflowsConfig
    ): void {
        $triggerConfigLoaded = false;
        $guardConfigLoaded   = false;
        foreach ($workflowsConfig as $workflowName => $workflowConfig) {
            if (!empty($workflowConfig['triggers'])) {
                if (!$triggerConfigLoaded) {
                    $loader->load('triggers.xml');
                    $triggerConfigLoaded = true;
                }
                $this->registerTriggerConfiguration($container, $workflowName, $workflowConfig['triggers']);
            }
            if (!empty($workflowConfig['guard'])) {
                if (!$guardConfigLoaded) {
                    $loader->load('guard.xml');
                    $guardConfigLoaded = true;
                }
                $this->registerGuardConfiguration($loader, $container, $workflowName, $workflowConfig['guard']);
            }
        }
    }

    /**
     * Adjusts trigger configurations
     *
     * @param ContainerBuilder $container       container
     * @param string           $workflowName    workflow name
     * @param array            $triggersConfig  triggers config
     */
    private function registerTriggerConfiguration(
        ContainerBuilder $container,
        string $workflowName,
        array $triggersConfig
    ): void {
        $workflowsWithScheduling = [];

        $actionListenerDefinition     = $container->findDefinition('gtt.workflow.trigger.event.listener.action');
        $expressionListenerDefinition = $container->findDefinition('gtt.workflow.trigger.event.listener.expression');
        $schedulerListenerDefinition  = $container->findDefinition('gtt.workflow.trigger.event.listener.scheduler');

        foreach ($triggersConfig as $triggerType => $triggerConfig) {
            switch ($triggerType) {
                case 'event':
                    foreach ($triggerConfig as $eventName => $eventConfig) {
                        $registerTriggerEventArgs = [$eventName, $workflowName, $eventConfig['subject_retrieving_expression']];

                        if (!empty($eventConfig['actions'])) {
                            $this->registerTriggerEventForListener(
                                $actionListenerDefinition,
                                $eventName,
                                array_merge($registerTriggerEventArgs, [$eventConfig['actions']])
                            );
                        }

                        if (!empty($eventConfig['expression'])) {
                            $this->registerTriggerEventForListener(
                                $expressionListenerDefinition,
                                $eventName,
                                array_merge($registerTriggerEventArgs, [$eventConfig['expression']])
                            );
                        }

                        if (!empty($eventConfig['schedule'])) {
                            $this->registerTriggerEventForListener(
                                $schedulerListenerDefinition,
                                $eventName,
                                array_merge($registerTriggerEventArgs, [$eventConfig['schedule']])
                            );

                            if (!in_array($workflowName, $workflowsWithScheduling)) {
                                $workflowsWithScheduling[] = $workflowName;
                            }
                        }
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
     * Configures event listener with particular event and arguments
     *
     * @param Definition $listenerDefinition listener definition
     * @param string     $eventName          event name
     * @param array      $arguments          arguments for event handling
     */
    private function registerTriggerEventForListener(Definition $listenerDefinition, $eventName, array $arguments): void
    {
        $listenerDefinition->addMethodCall('registerEvent', $arguments);
        $listenerDefinition->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'dispatchEvent']);
    }

    /**
     * Adjusts scheduler configurations
     *
     * @param LoaderInterface  $loader          config loader
     * @param ContainerBuilder $container       container
     * @param array            $schedulerConfig scheduler config
     */
    private function registerSchedulerConfiguration(LoaderInterface $loader, ContainerBuilder $container, array $schedulerConfig): void
    {
        if (!$schedulerConfig) {
            throw new InvalidConfigurationException('"scheduler" section must be configured');
        }

        $loader->load("scheduler.xml");

        $schedulerDefinition      = $container->findDefinition('gtt.workflow.action_scheduler');
        $schedulerEntityManagerId = sprintf('doctrine.orm.%s_entity_manager', $schedulerConfig['entity_manager']);

        $schedulerDefinition->replaceArgument(0, new Reference($schedulerEntityManagerId));
    }

    /**
     * Adjusts guard configurations
     *
     * @param LoaderInterface  $loader       config loader
     * @param ContainerBuilder $container    container
     * @param string           $workflowName workflow name
     * @param array            $guardConfig  workflow trigger config
     */
    private function registerGuardConfiguration(LoaderInterface $loader, ContainerBuilder $container, $workflowName, $guardConfig): void
    {
        $loader->load('guard.xml');

        $guardDefinition = $container->findDefinition(ExpressionGuard::class);

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
    private function registerGuardListener($guardDefinition, $eventName, $workflowName, $expression): void
    {
        $guardDefinition->addMethodCall('registerGuardExpression', [$eventName, $workflowName, $expression]);
        $guardDefinition->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'guardTransition']);
    }

    /**
     * Register configuration for actions section
     *
     * @param array            $actionConfigs actions config
     * @param ContainerBuilder $container     container
     */
    private function registerActionsConfiguration(array $actionConfigs, ContainerBuilder $container): void
    {
        $registryDefinition = $container->findDefinition('gtt.workflow.action.registry');

        foreach ($actionConfigs as $actionName => $actionConfig) {
            $actionReferenceDefinition = new ChildDefinition('gtt.workflow.action.callable_method.reference.prototype');
            $actionReferenceDefinition->setArguments(
                [
                    [
                        isset($actionConfig['service'])
                            ? new Reference($actionConfig['service'])
                            : $actionConfig['class'],
                        $actionConfig['method']
                    ],
                    $actionConfig['type']]
            );

            $registryDefinition->addMethodCall('add', [$actionName, $actionReferenceDefinition]);
        }
    }

    /**
     * Defines internal container with services
     *
     * @param ContainerBuilder $container The container
     * @param array            $context   Context where keys are service aliases and values are service identifiers
     *
     * @return void
     */
    private function registerContextConfiguration(ContainerBuilder $container, array $context): void
    {
        $services = [];
        $context = [
            'gtt.workflow.action.executor' => 'gtt.workflow.action.executor'
        ] + $context;
        foreach ($context as $alias => $serviceId) {
            $services[$alias] = new Reference($serviceId);
        }

        $container->getDefinition('gtt.workflow.context_container')->setArgument(0, $services);
    }
}
