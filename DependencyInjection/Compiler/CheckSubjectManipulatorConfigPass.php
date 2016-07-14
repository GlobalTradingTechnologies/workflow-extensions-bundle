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
     * Workflow registry definition id
     */
    const WORKFLOW_REGISTRY_ID= 'workflow.registry';

    /**
     * Workflow id prefix used in main workflow bundle
     */
    const WORKFLOW_ID_PREFIX = 'workflow.';

    /**
     * Name of the method used to register workflows in registry
     */
    const WORKFLOW_REGISTRY_ADD_WORKFLOW_METHOD_NAME = "add";

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition(static::WORKFLOW_REGISTRY_ID)) {
            return;
        }

        if (false === $container->hasDefinition('gtt.workflow.transition_scheduler')) {
            return;
        }

        $subjectClassesWithSubjectFromDomain = $container->getParameter('gtt.workflow.subject_classes_with_subject_from_domain');
        $workflowsWithScheduling             = $container->getParameter('gtt.workflow.workflows_with_scheduling');

        $registryDefinition = $container->getDefinition(self::WORKFLOW_REGISTRY_ID);
        foreach ($registryDefinition->getMethodCalls() as $call) {
            if ($call[0] == self::WORKFLOW_REGISTRY_ADD_WORKFLOW_METHOD_NAME) {
                $callArgs = $call[1];

                if (count($callArgs) != 2 || !$callArgs[0] instanceof Reference || !is_string($callArgs[1])) {
                    throw new RuntimeException(
                        sprintf(
                            'Workflow registry service have unsupported signature for "%s" method',
                            self::WORKFLOW_REGISTRY_ADD_WORKFLOW_METHOD_NAME
                        )
                    );
                }

                /** @var Reference $workflowReference */
                $workflowReference      = $callArgs[0];
                $workflowSupportedClass = $callArgs[1];

                $workflowIdWithPrefix =  (string) $workflowReference;
                if (substr($workflowIdWithPrefix, 0, strlen(self::WORKFLOW_ID_PREFIX)) != self::WORKFLOW_ID_PREFIX) {
                    throw new RuntimeException(
                        sprintf(
                            "Workflow registry works with workflow id '%s'in unsupported format",
                            $workflowIdWithPrefix
                        )
                    );
                }

                $workflowId = substr($workflowIdWithPrefix, strlen(self::WORKFLOW_ID_PREFIX));

                if (in_array($workflowId, $workflowsWithScheduling) &&
                    !in_array(ltrim($workflowSupportedClass, "\\"), $subjectClassesWithSubjectFromDomain)) {
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