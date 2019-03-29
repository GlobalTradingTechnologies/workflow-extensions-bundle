<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date   08.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Compiler;

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\RuntimeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Checks that all necessary options for scheduler are set for classes supported by corresponding workflows
 */
class CheckSubjectManipulatorConfigPass implements CompilerPassInterface
{
    /**
     * Workflow id prefix used in main workflow bundle
     */
    public const WORKFLOW_ID_PREFIX = 'workflow.';

    /**
     * Name of the method used to register workflows in registry
     */
    public const WORKFLOW_REGISTRY_ADD_WORKFLOW_METHOD_NAME = "add";

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('workflow.registry')) {
            return;
        }

        if (false === $container->hasDefinition('gtt.workflow.transition_scheduler')) {
            return;
        }

        $subjectClassesWithSubjectFromDomain = $container->getParameter('gtt.workflow.subject_classes_with_subject_from_domain');
        $workflowsWithScheduling             = $container->getParameter('gtt.workflow.workflows_with_scheduling');

        $registryDefinition = $container->getDefinition('workflow.registry');
        foreach ($registryDefinition->getMethodCalls() as [$call, $callArgs]) {
            if ($call === self::WORKFLOW_REGISTRY_ADD_WORKFLOW_METHOD_NAME) {
                if (count($callArgs) !== 2 || !$callArgs[0] instanceof Reference || !is_string($callArgs[1])) {
                    throw new RuntimeException(
                        sprintf(
                            'Workflow registry service have unsupported signature for "%s" method',
                            self::WORKFLOW_REGISTRY_ADD_WORKFLOW_METHOD_NAME
                        )
                    );
                }

                /** @var Reference $workflowReference */
                [$workflowReference, $workflowSupportedClass] = $callArgs;

                $workflowIdWithPrefix =  (string) $workflowReference;
                if (strpos($workflowIdWithPrefix, self::WORKFLOW_ID_PREFIX) !== 0) {
                    throw new RuntimeException(
                        sprintf(
                            "Workflow registry works with workflow id '%s'in unsupported format",
                            $workflowIdWithPrefix
                        )
                    );
                }

                $workflowId = substr($workflowIdWithPrefix, strlen(self::WORKFLOW_ID_PREFIX));

                if (\in_array($workflowId, $workflowsWithScheduling) &&
                    !\in_array(ltrim($workflowSupportedClass, "\\"), $subjectClassesWithSubjectFromDomain)) {
                    throw new InvalidConfigurationException(
                        sprintf(
                            'Workflow "%s" configured to use scheduler so all the supported subject classes for it'.
                            'must be configured with "subject_from_domain" option under "subject_manipulator". '.
                            'This option for "%s" class is missing.',
                            $workflowId,
                            $workflowSupportedClass
                        )
                    );
                }
            }
        }
    }
}
