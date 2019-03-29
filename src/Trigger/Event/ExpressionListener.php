<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 29.06.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Trigger\Event;

use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;

/**
 * Evaluates expression as a result of event fired
 */
class ExpressionListener extends AbstractActionListener
{
    /**
     * Expression language for execute expressions with actions
     *
     * @var ExpressionLanguage
     */
    private $actionLanguage;

    /**
     * AbstractListener constructor.
     *
     * @param ExpressionLanguage $subjectRetrieverLanguage subject retriever expression language
     * @param SubjectManipulator $subjectManipulator       subject manipulator
     * @param Registry           $workflowRegistry         workflow registry
     * @param LoggerInterface    $logger                   logger
     * @param ExpressionLanguage $actionLanguage           action expression language
     */
    public function __construct(
        ExpressionLanguage $subjectRetrieverLanguage,
        SubjectManipulator $subjectManipulator,
        Registry $workflowRegistry,
        LoggerInterface $logger,
        ExpressionLanguage $actionLanguage
    ) {
        parent::__construct($subjectRetrieverLanguage, $subjectManipulator, $workflowRegistry, $logger, $actionLanguage);
        $this->actionLanguage = $actionLanguage;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $expression expression to be executed by event
     */
    public function registerEvent(
        string $eventName,
        string $workflowName,
        string $subjectRetrievingExpression,
        string $expression)
    {
        $this->configureSubjectRetrievingForEvent($eventName, $workflowName, $subjectRetrievingExpression);
        $this->supportedEventsConfig[$eventName][$workflowName]['expression'] = $expression;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleEvent(string $eventName, Event $event, array $eventConfigForWorkflow, WorkflowContext $workflowContext): void
    {
        $expression = $this->supportedEventsConfig[$eventName][$workflowContext->getWorkflow()->getName()]['expression'];

        $this->execute(
            function () use ($expression, $event, $workflowContext) {
                $this->actionLanguage->evaluate($expression, ['event' => $event, 'workflowContext' => $workflowContext]);
            },
            $eventName,
            $workflowContext,
            sprintf('execute expression "%s"', $expression)
        );
    }
}
