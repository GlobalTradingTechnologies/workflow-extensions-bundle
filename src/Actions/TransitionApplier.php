<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 03.08.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Actions;

use Exception;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Exception\LogicException as WorkflowLogicException;
use Throwable;

/**
 * Applies workflow transitions
 */
class TransitionApplier
{
    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * TransitionApplier constructor.
     *
     * @param LoggerInterface $logger logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Applies single transition
     *
     * @param WorkflowContext $workflowContext workflow context
     * @param string          $transition      transition to be applied
     */
    public function applyTransition(WorkflowContext $workflowContext, string $transition): void
    {
        $this->applyTransitions($workflowContext, [$transition]);
    }

    /**
     * Applies list of transitions
     *
     * @param WorkflowContext $workflowContext workflow context
     * @param array           $transitions     list of transitions to be applied
     * @param bool            $cascade         if this flag is set all the available transitions should be applied (it
     *                                         may be cascade); otherwise the first applied transition breaks execution
     */
    public function applyTransitions(WorkflowContext $workflowContext, array $transitions = [], bool $cascade = false): void
    {
        if (!$transitions) {
            return;
        }

        $workflow      = $workflowContext->getWorkflow();
        $subject       = $workflowContext->getSubject();
        $loggerContext = $workflowContext->getLoggerContext();

        $this->logger->debug('Resolved workflow for subject', $loggerContext);

        $applied = false;
        foreach ($transitions as $transition) {
            try {
                // We do not call Workflow:can method here due to performance reasons in order to prevent
                // execution of all the doCan-listeners (guards) in case when transition can be applied.
                // Therefore we catch LogicException and interpreting it as case when transition can not be applied
                $workflow->apply($subject, $transition);
                $this->logger->info(sprintf('Workflow successfully applied transition "%s"', $transition), $loggerContext);
                $applied = true;
                if (!$cascade) {
                    break;
                }
            } catch (WorkflowLogicException $e) {
                // transition cannot be applied because it is not allowed
                $this->logger->info(
                    sprintf('Workflow transition "%s" cannot be applied due to it is not allowed', $transition),
                    $loggerContext
                );
            } catch (Exception $e) {
                $this->logger->error(
                    sprintf('Workflow cannot apply transition "%s" due to exception. Details: %s', $transition, $e->getMessage()),
                    $loggerContext
                );
                throw $e;
            } catch (Throwable $e) {
                $this->logger->critical(
                    sprintf('Workflow cannot apply transition "%s" due to error. Details: %s', $transition, $e->getMessage()),
                    $loggerContext
                );
                throw $e;
            }
        }

        if (!$applied) {
            $this->logger->warning('All transitions to apply are not allowed', $loggerContext);
        }
    }
}
