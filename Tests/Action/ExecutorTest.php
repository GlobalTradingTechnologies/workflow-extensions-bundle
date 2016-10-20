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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Tests\Action;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\ActionReference;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Executor;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Registry;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Workflow\Workflow;

class ExecutorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider actionProvider
     */
    public function testEvaluatesAction($actionReferenceType, $inputArgs, $expectedArgs, WorkflowContext $wc)
    {
        $container = $this->getMock(ContainerInterface::class);

        $actionName = "a1";
        $actionReference = $this->getMockBuilder(ActionReference::class)->disableOriginalConstructor()->getMock();
        $actionReference->expects(self::once())->method('setContainer')->with(self::equalTo($container));
        $actionReference->expects(self::once())->method('invoke')->with($expectedArgs);
        $actionReference->expects(self::once())->method('getType')->willReturn($actionReferenceType);

        $actionRegistry = $this->getMock(Registry::class);
        $actionRegistry->expects(self::once())->method('get')->with(self::equalTo($actionName))->willReturn($actionReference);

        $executor = new Executor($actionRegistry, $container);
        $executor->execute($wc, $actionName, $inputArgs);
    }

    public function actionProvider()
    {
        $data = [];

        $workflowContext = new WorkflowContext(
            $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock(),
            new \StdClass(),
            1
        );

        $defaultInputActionArgs = ['some' => 'arg', "more"];

        // regular action
        $data[] = [ActionReference::TYPE_REGULAR, $defaultInputActionArgs, $defaultInputActionArgs, $workflowContext];

        // workflow action
        $workflowActionInputArgs = $defaultInputActionArgs;
        array_unshift($workflowActionInputArgs, $workflowContext);
        $data[] = [ActionReference::TYPE_WORKFLOW, $defaultInputActionArgs, $workflowActionInputArgs, $workflowContext];

        return $data;
    }
}
