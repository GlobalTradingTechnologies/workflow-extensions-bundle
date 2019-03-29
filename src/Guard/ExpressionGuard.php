<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 14.07.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Guard;

use Gtt\Bundle\WorkflowExtensionsBundle\Exception\UnsupportedGuardEventException;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Registry as WorkflowRegistry;
use Throwable;

/**
 * Listener for workflow guard events that can block or allow transition with specified expression
 */
class ExpressionGuard
{
    /**
     * Expression language
     *
     * @var ExpressionLanguage
     */
    private $language;

    /**
     * Subject manipulator
     *
     * @var SubjectManipulator
     */
    private $subjectManipulator;

    /**
     * Workflow registry
     *
     * @var WorkflowRegistry
     */
    private $workflowRegistry;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Maps guard event name to expression and workflow used to block or allow transition
     *
     * @var array
     */
    private $supportedEventsConfig = [];

    /**
     * ExpressionGuard constructor
     *
     * @param ExpressionLanguage $language expression language
     * @param SubjectManipulator $subjectManipulator  subject manipulator
     * @param WorkflowRegistry   $workflowRegistry    workflow registry
     * @param LoggerInterface    $logger              logger
     */
    public function __construct(
        ExpressionLanguage $language,
        SubjectManipulator $subjectManipulator,
        WorkflowRegistry $workflowRegistry,
        LoggerInterface $logger
    ) {
        $this->language           = $language;
        $this->subjectManipulator = $subjectManipulator;
        $this->workflowRegistry   = $workflowRegistry;
        $this->logger             = $logger;
    }

    /**
     * Registers guard expression
     *
     * @param string $eventName    guard event name
     * @param string $workflowName workflow name
     * @param string $expression   guard expression
     */
    public function registerGuardExpression(string $eventName, string $workflowName, string $expression): void
    {
        $this->supportedEventsConfig[$eventName] = [$workflowName, $expression];
    }

    /**
     * Blocks or allows workflow transitions by guard expression evaluation
     *
     * @param GuardEvent $event
     * @param string     $eventName
     *
     * @throws \Exception in case of failure
     */
    public function guardTransition(GuardEvent $event, string $eventName): void
    {
        if (!array_key_exists($eventName, $this->supportedEventsConfig)) {
            throw new UnsupportedGuardEventException(sprintf("Cannot find registered guard event by name '%s'", $eventName));
        }

        [$workflowName, $expression] = $this->supportedEventsConfig[$eventName];
        $subject = $event->getSubject();
        $workflowContext = new WorkflowContext(
            $this->workflowRegistry->get($subject, $workflowName),
            $subject,
            $this->subjectManipulator->getSubjectId($subject)
        );
        $loggerContext = $workflowContext->getLoggerContext();

        $errorMessage      = null;

        try {
            $expressionResult = $this->language->evaluate($expression, ['event' => $event]);
        } catch (Throwable $e) {
            $errorMessage = sprintf(
                "Guard expression '%s' for guard event '%s' cannot be evaluated. Details: '%s'",
                $expression,
                $eventName,
                $e->getMessage()
            );

            $this->logger->error($errorMessage, $loggerContext);

            // simply skipping processing here without blocking transition
            return;
        }

        if (!is_bool($expressionResult)) {
            $this->logger->debug(
                sprintf("Guard expression '%s' for guard event '%s' evaluated with non-boolean result".
                    " and will be converted to boolean", $expression, $eventName),
                $loggerContext
            );

            $expressionResult = (bool) $expressionResult;
        }

        $event->setBlocked($expressionResult);

        if ($expressionResult) {
            $this->logger->debug(
                sprintf("Transition '%s' is blocked by guard expression '%s' for guard event '%s'",
                    $event->getTransition()->getName(),
                    $expression,
                    $eventName
                ),
                $loggerContext
            );
        }
    }
}
