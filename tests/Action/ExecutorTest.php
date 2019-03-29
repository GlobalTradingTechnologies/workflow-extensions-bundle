<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 19.10.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ActionReferenceInterface;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Workflow\Workflow;

class ExecutorTest extends TestCase
{
    /**
     * @dataProvider actionReferenceProvider
     */
    public function testEvaluatesAction(
        string $actionReferenceType,
        array $inputArgs,
        array $expectedArgs,
        WorkflowContext $wc
    ): void {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMockForAbstractClass();

        $actionName = 'a1';
        $actionReference = $this->getMockBuilder(ActionReferenceInterface::class)->disableOriginalConstructor()->getMock();
        $actionReference->expects(self::once())->method('invoke')->with($expectedArgs);
        $actionReference->expects(self::once())->method('getType')->willReturn($actionReferenceType);

        $actionRegistry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $actionRegistry->expects(self::once())->method('get')->with(self::equalTo($actionName))->willReturn($actionReference);

        $executor = new Executor($actionRegistry, $container);
        $executor->execute($wc, $actionName, $inputArgs);
    }

    public function actionReferenceProvider(): array
    {
        $data = [];

        $workflowContext = new WorkflowContext(
            $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock(),
            new \StdClass(),
            1
        );

        $defaultInputActionArgs = ['some' => 'arg', 'more'];

        // regular action (non-container-aware)
        $data[] = [ActionReferenceInterface::TYPE_REGULAR, $defaultInputActionArgs, $defaultInputActionArgs, $workflowContext];

        // regular action (container-aware)
        $data[] = [ActionReferenceInterface::TYPE_REGULAR, $defaultInputActionArgs, $defaultInputActionArgs, $workflowContext];

        $workflowActionInputArgs = $defaultInputActionArgs;
        array_unshift($workflowActionInputArgs, $workflowContext);

        // workflow action (non-container-aware)
        $data[] = [ActionReferenceInterface::TYPE_WORKFLOW, $defaultInputActionArgs, $workflowActionInputArgs, $workflowContext];

        // workflow action (container-aware)
        $data[] = [ActionReferenceInterface::TYPE_WORKFLOW, $defaultInputActionArgs, $workflowActionInputArgs, $workflowContext];

        return $data;
    }
}
