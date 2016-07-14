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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Command;

use Gtt\Bundle\WorkflowExtensionsBundle\SubjectManipulator;
use Gtt\Bundle\WorkflowExtensionsBundle\TransitionApplier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command is a part of Schedule subsystem.
 * Used to apply scheduled transitions as async jobs executed by scheduler.
 *
 * WARNING! The command can only be used when scheduler system activated
 * (ie there is at least one scheduled transition in config).
 * Also this command does not output anything even in case of failure and reports execution results
 * into log files instead.
 */
class TriggerTransitionCommand extends Command
{
    /**
     * Subject manipulator
     *
     * @var SubjectManipulator
     */
    private $subjectManipulator;

    /**
     * Transition applier
     *
     * @var TransitionApplier
     */
    private $transitionApplier;

    /**
     * TriggerTransitionCommand constructor.
     *
     * @param SubjectManipulator $subjectManipulator subject manipulator
     * @param TransitionApplier  $transitionApplier  transition applier
     */
    public function __construct(SubjectManipulator $subjectManipulator, TransitionApplier $transitionApplier)
    {
        $this->subjectManipulator = $subjectManipulator;
        $this->transitionApplier  = $transitionApplier;

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
                    'transition',
                    't',
                    InputOption::VALUE_REQUIRED,
                    "Transition name should be applied"
                ),
                new InputOption(
                    'workflow',
                    'w',
                    InputOption::VALUE_REQUIRED,
                    "Name of the workflow that should apply the transition"
                ),
                new InputOption(
                    'subjectId',
                    'sid',
                    InputOption::VALUE_REQUIRED,
                    "Id of the subject"
                ),
                new InputOption(
                    'subjectClass',
                    null,
                    InputOption::VALUE_REQUIRED,
                    "FQCN of the subject"
                )
            ))
            ->setName('workflow:transition:trigger')
            ->setDescription('Transition trigger command')
            ->setHelp(<<<EOT
This <info>%command.name%</info> allows trigger transition for specified subject and workflow.
Warning! <info>%command.name%</info> can only be used when scheduler system activated
(ie there is at least one scheduled transition in config). 
Also this command does not output anything even in case of failure and reports execution results 
into log files instead.
EOT
            );
    }

    /**
     * Tries to apply transition by options specified
     *
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $transitionName = $input->getOption('transition');
        $workflowName   = $input->getOption('workflow');
        $subjectClass   = $input->getOption('subjectClass');
        $subjectId      = (int) $input->getOption('subjectId');

        $subject = $this->subjectManipulator->getSubjectFromDomain($subjectClass, $subjectId);

        $this->transitionApplier->applyTransition($subject, $workflowName, $transitionName);
    }
}