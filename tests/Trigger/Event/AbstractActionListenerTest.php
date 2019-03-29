<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 17.10.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Trigger\Event;

use Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Enum\ActionArgumentTypes;
use Gtt\Bundle\WorkflowExtensionsBundle\Exception\ActionException;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Registry;

class AbstractActionListenerTest extends TestCase
{
    /**
     * @dataProvider argumentsProvider
     */
    public function testResolveActionArguments(array $inputArgArray, $expectedResult, ExpressionLanguage $actionLanguage = null): void
    {
        if ($actionLanguage) {
            /** @var AbstractActionListener $listener */
            $listener = $this->getMockForAbstractClass(
                AbstractActionListener::class,
                [
                    $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock(),
                    $this->getMockBuilder(SubjectManipulator::class)->disableOriginalConstructor()->getMock(),
                    $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock(),
                    $this->getMockBuilder(LoggerInterface::class)->disableOriginalConstructor()->getMock(),
                    $actionLanguage
                ]
            );
        } else {
            /** @var AbstractActionListener $listener */
            $listener = $this->getMockForAbstractClass(AbstractActionListener::class, [], "", false);
        }

        $resolveActionArgumentsMethodRef = new ReflectionMethod($listener, 'resolveActionArguments');
        $resolveActionArgumentsMethodRef->setAccessible(true);

        $invokeArgs = [
            'actionName',
            $inputArgArray,
            new Event(),
            $this->getMockBuilder(WorkflowContext::class)->disableOriginalConstructor()->getMock()
        ];
        if ($expectedResult instanceof \Exception) {
            $this->expectException(get_class($expectedResult));
            $resolveActionArgumentsMethodRef->invokeArgs($listener, $invokeArgs);
        } else {
            self::assertEquals($expectedResult, $resolveActionArgumentsMethodRef->invokeArgs($listener, $invokeArgs));
        }
    }

    public function argumentsProvider(): array
    {
        $data = [];

        // correct deep array
        $validExpression1 = 'e1';
        $validExpression2 = 'e2';
        $expressionResult1 = 'r1';
        $expressionResult2 = 'r2';
        $actionLanguage = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $actionLanguage->expects(self::exactly(2))->method('evaluate')->will(
            self::onConsecutiveCalls($expressionResult1, $expressionResult2)
        );

        $data[] = [
            [
                [
                    'type'  => ActionArgumentTypes::TYPE_EXPRESSION,
                    'value' => $validExpression1
                ],
                [
                    'type'  => ActionArgumentTypes::TYPE_SCALAR,
                    'value' => 123e4
                ],
                [
                    'type'  => ActionArgumentTypes::TYPE_ARRAY,
                    'value' =>
                        [
                            [
                                'type'  => ActionArgumentTypes::TYPE_ARRAY,
                                'value' =>
                                    [
                                        [
                                            'type'  => ActionArgumentTypes::TYPE_EXPRESSION,
                                            'value' => $validExpression2
                                        ]
                                    ]
                            ],
                            [
                                'type'  => ActionArgumentTypes::TYPE_SCALAR,
                                'value' => "string"
                            ],
                            [
                                'type'  => ActionArgumentTypes::TYPE_SCALAR,
                                'value' => 12
                            ]
                        ]
                ]
            ],
            [
                $expressionResult1,
                123e4,
                [
                    [
                        $expressionResult2
                    ],
                    'string',
                    12
                ]
            ],
            $actionLanguage
        ];

        // invalid expression 1
        $actionLanguage = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $actionLanguage->expects(self::once())->method('evaluate')->willReturn(new \StdClass());
        $data[] = [
            [
                [
                    'type'  => ActionArgumentTypes::TYPE_EXPRESSION,
                    'value' => "some exp"
                ]
            ],
            new ActionException(),
            $actionLanguage
        ];

        // invalid expression 2
        $actionLanguage = $this->getMockBuilder(ExpressionLanguage::class)->disableOriginalConstructor()->getMock();
        $actionLanguage->expects(self::once())->method('evaluate')->willReturn(["test" => 1]);
        $data[] = [
            [
                [
                    'type'  => ActionArgumentTypes::TYPE_EXPRESSION,
                    'value' => "some exp"
                ]
            ],
            new ActionException(),
            $actionLanguage
        ];

        // associative array keys are dropped
        $data[] = [
            [
                [
                    'type'  => ActionArgumentTypes::TYPE_ARRAY,
                    'value' => [
                        "test" => [
                            'type'  => ActionArgumentTypes::TYPE_SCALAR,
                            'value' => 123
                        ]
                    ]
                ]
            ],
            [[123]]
        ];

        return $data;
    }
}
