<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection;

use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\EventTriggerWithGuardsCase\Fixtures\TargetWorkflowSubject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * Class ConfigurationTest
 */
class ConfigurationTest extends TestCase
{
    /**
     * @param array $config
     * @param array $expectedConfig
     *
     * @dataProvider contextDataProvider
     */
    public function testContextDefinition(array $config, array $expectedConfig): void
    {
        self::assertSame($expectedConfig, $this->processConfiguration($config)['context']);
    }

    public function contextDataProvider(): iterable
    {
        $nomatter = ['workflows' => ['some' => []], 'subject_manipulator' => [TargetWorkflowSubject::class => null]];

        yield [['context' => 'my_service'] + $nomatter, ['my_service' => 'my_service']];

        yield [['context' => null] + $nomatter, []];

        yield [$nomatter, []];

        yield [['context' => ['test' => null]] + $nomatter, ['test' => 'test']];

        yield [['context' => ['test' => 'another']] + $nomatter, ['test' => 'another']];
    }

    private function processConfiguration(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
