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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional;

use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\EventTriggerWithGuardsCase\Fixtures\Event;
use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\EventTriggerWithGuardsCase\Fixtures\TargetWorkflowSubject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventTriggerWithGuardsCaseTest extends TestCase
{
    /**
     * WebClient emulator
     *
     * @var \Symfony\Bundle\FrameworkBundle\Client
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient(
            array(
                "app_name" => "EventTriggerWithGuardsCaseTest",
                "test_case" => "EventTriggerWithGuardsCase",
                "root_config" => "config.yml",
                "config_dir" => __DIR__ . "/Configuration",
                "environment" => "test",
                "debug" => false
            )
        );
    }

    /**
     * @group functional
     */
    public function testSimple(): void
    {
        $container = $this->client->getContainer();
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $container->get('event_dispatcher');
        
        $subject = new TargetWorkflowSubject();
        $event = new Event($subject);
        
        $eventDispatcher->dispatch('processing.event', $event);
        self::assertEquals(['processing' => 1], $subject->getStatus());

        $eventDispatcher->dispatch('processing.event', $event);
        self::assertEquals(['active' => 1, "regular" => 1], $subject->getStatus());

        $eventDispatcher->dispatch('viping.event', $event);
        self::assertEquals(['active' => 1, 'vip' => 1], $subject->getStatus());

        $eventDispatcher->dispatch('processing.event', $event);
        // nothing happens
        self::assertEquals(['active' => 1, 'vip' => 1], $subject->getStatus());

        $eventDispatcher->dispatch('viping.event', $event);
        // guard prevents closing vips
        self::assertEquals(['active' => 1, 'vip' => 1], $subject->getStatus());

        $eventDispatcher->dispatch('crazy_closing.event', $event);
        // guard allows closing only if there is only one place "active" in marking
        self::assertEquals(['active' => 1, 'vip' => 1], $subject->getStatus());
    }
}
