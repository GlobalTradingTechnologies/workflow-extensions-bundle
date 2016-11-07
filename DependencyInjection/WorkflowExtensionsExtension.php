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

use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ServiceMethod;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\StaticMethod;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
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
        array $subjectManipulatorConfig)
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
     * Registers workflows configuration section
     *
     * @param LoaderInterface  $loader          config loader
     * @param ContainerBuilder $container       container
     * @param array            $workflowsConfig workflows config
     */
    private function registerWorkflowsConfiguration(LoaderInterface $loader, ContainerBuilder $container, array $workflowsConfig)
    {
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
    private function registerTriggerConfiguration(ContainerBuilder $container, $workflowName, array $triggersConfig)
    {
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
    private function registerTriggerEventForListener(Definition $listenerDefinition, $eventName, array $arguments)
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
    private function registerSchedulerConfiguration(LoaderInterface $loader, ContainerBuilder $container, array $schedulerConfig)
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
    private function registerGuardConfiguration(LoaderInterface $loader, ContainerBuilder $container, $workflowName, $guardConfig)
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

    /**
     * Register configuration for actions section
     *
     * @param array            $actionConfigs actions config
     * @param ContainerBuilder $container     container
     */
    private function registerActionsConfiguration(array $actionConfigs, ContainerBuilder $container)
    {
        $registryDefinition = $container->findDefinition('gtt.workflow.action.registry');

        foreach ($actionConfigs as $actionName => $actionConfig) {
            // here we explicitly set parent class to decorated definition in order to fix inconsistent behavior for <= 2.7
            // see https://github.com/symfony/symfony/issues/17353 and https://github.com/symfony/symfony/pull/15096
            if (array_key_exists('service', $actionConfig)) {
                $actionReferenceDefinition = new DefinitionDecorator('gtt.workflow.action.service_method.reference.prototype');
                $actionReferenceDefinition->setClass(ServiceMethod::class);
                $actionReferenceDefinition->setArguments([$actionConfig['method'], $actionConfig['service'], $actionConfig['type']]);
            } else {
                $actionReferenceDefinition = new DefinitionDecorator('gtt.workflow.action.static_method.reference.prototype');
                $actionReferenceDefinition->setClass(StaticMethod::class);
                $actionReferenceDefinition->setArguments([$actionConfig['method'], $actionConfig['class'], $actionConfig['type']]);
            }

            $registryDefinition->addMethodCall('add', [$actionName, $actionReferenceDefinition]);
        }
    }
}