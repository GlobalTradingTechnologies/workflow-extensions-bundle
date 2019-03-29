<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 28.07.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Command;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\Executor as ActionExecutor;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Workflow\Registry as WorkflowRegistry;

/**
 * Console command executes action
 *
 * Useful for scheduled execution
 */
class ExecuteActionCommand extends Command
{
    /**
     * Command name
     */
    protected static $defaultName = 'workflow:action:execute';

    /**
     * Action executor
     *
     * @var ActionExecutor
     */
    private $actionExecutor;

    /**
     * Workflow registry
     *
     * @var WorkflowRegistry
     */
    private $workflowRegistry;

    /**
     * Subject manipulator
     *
     * @var SubjectManipulator
     */
    private $subjectManipulator;

    /**
     * ExecuteActionCommand constructor.
     *
     * @param ActionExecutor     $actionExecutor
     * @param WorkflowRegistry   $workflowRegistry
     * @param SubjectManipulator $subjectManipulator
     */
    public function __construct(ActionExecutor $actionExecutor, WorkflowRegistry $workflowRegistry, SubjectManipulator $subjectManipulator)
    {
        $this->actionExecutor     = $actionExecutor;
        $this->workflowRegistry   = $workflowRegistry;
        $this->subjectManipulator = $subjectManipulator;

        parent::__construct();
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption(
                    'action',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Action name should be executed'
                ),
                new InputOption(
                    'arguments',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Json-encoded list of action parameters'
                ),
                new InputOption(
                    'workflow',
                    'w',
                    InputOption::VALUE_REQUIRED,
                    'Name of the current workflow'
                ),
                new InputOption(
                    'subjectId',
                    'sid',
                    InputOption::VALUE_REQUIRED,
                    'Id of the workflow subject'
                ),
                new InputOption(
                    'subjectClass',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'FQCN of the workflow subject'
                )
            ))
            ->setDescription('Execute action command')
            ->setHelp(<<<EOT
This <info>%command.name%</info> executes action by name with parameters specified
EOT
            );
    }

    /**
     * Tries to execute action specified
     *
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $actionName        = $input->getOption('action');
        $encodedParameters = $input->getOption('arguments');
        $parameters        = json_decode($encodedParameters);

        $workflowName = $input->getOption('workflow');
        $subjectClass = $input->getOption('subjectClass');
        $subjectId    = $input->getOption('subjectId');

        $subject  = $this->subjectManipulator->getSubjectFromDomain($subjectClass, $subjectId);
        $workflowContext = new WorkflowContext($this->workflowRegistry->get($subject, $workflowName), $subject, $subjectId);

        $this->actionExecutor->execute($workflowContext, $actionName, $parameters);
    }
}
