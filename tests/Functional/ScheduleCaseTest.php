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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional;

use Doctrine\Bundle\DoctrineBundle\Command\Proxy\CreateSchemaDoctrineCommand;
use Doctrine\Bundle\FixturesBundle\Command\LoadDataFixturesDoctrineCommand;
use Doctrine\ORM\EntityManager;
use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures\ClientBundle\ClientBundle;
use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures\ClientBundle\Entity\Client;
use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures\Event;
use JMS\JobQueueBundle\Command\RunCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ScheduleCaseTest extends TestCase
{
    /**
     * WebClient emulator
     *
     * @var \Symfony\Bundle\FrameworkBundle\Client
     */
    protected $client;

    /**
     * @var Application
     */
    protected $app;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        $this->initApplication();

        $this->initDbSchema();

        // load fixtures
        $fixturesBundle = new ClientBundle();
        $fixturesPath = $fixturesBundle->getPath() . '/DataFixtures/ORM';
        $this->loadFixtures($this->client->getContainer(), $fixturesPath);
    }

    public function initApplication(): Application
    {
        if (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers())) {
            self::markTestSkipped('This test requires SQLite support in your environment');
        }

        parent::setUp();

        $this->client = self::createClient(
            array(
                "app_name"    => "ScheduleCaseTest",
                "test_case"   => "ScheduleCase",
                "root_config" => "config.yml",
                "config_dir"  => __DIR__ . "/Configuration",
                "root_dir"    => __DIR__ . "/Configuration/ScheduleCase",
                "environment" => "test",
                "debug"       => false
            )
        );
        $this->app = new Application($this->client->getKernel());
        // add jms-job-id option to the application in order to be able to run scheduler
        $this->app->getDefinition()->addOption(
            new InputOption('--jms-job-id', null, InputOption::VALUE_REQUIRED, 'The ID of the Job.')
        );
        $this->app->setAutoExit(false);
        $this->app->setCatchExceptions(false);

        return $this->app;
    }

    /**
     * @param string $em
     */
    protected function initDbSchema($em = 'default'): void
    {
        $schemaCreateCommand = new CreateSchemaDoctrineCommand();
        $this->runConsoleCommand($schemaCreateCommand, ["--em" => $em]);
    }

    /**
     * @param ContainerInterface $container
     * @param array|string|false $fixturesPath
     * @param string $em
     * @param bool|true $append
     *
     * @return void
     */
    protected function loadFixtures(
        ContainerInterface $container,
        $fixturesPath = false,
        string $em = 'default',
        bool $append = true
    ): void {
        $fixtureLoadCommand = new LoadDataFixturesDoctrineCommand();
        $fixtureLoadCommand->setContainer($container);
        $params = array(
            "--em" => $em,
            "--append" => $append
        );
        if ($fixturesPath) {
            $params["--fixtures"] = $fixturesPath;
        }

        $this->runConsoleCommand($fixtureLoadCommand, $params);
    }

    /**
     * @large
     * @group functional
     */
    public function testScheduleWorks(): void
    {
        $container = $this->client->getContainer();
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $container->get('event_dispatcher');

        $_SERVER['SYMFONY_CONSOLE_FILE'] = $container->getParameter('kernel.root_dir') . DIRECTORY_SEPARATOR . 'console';

        /** @var EntityManager $clientEm */
        $clientEm   = $container->get("doctrine")->getManagerForClass(Client::class);
        $clientRepo = $clientEm->getRepository(Client::class);
        /** @var Client $subject */
        $subject = $clientRepo->findOneBy(['name' => "Johnny"]);
        $event = new Event($subject);

        // simply check that client can be activated (also sleeping transition should be scheduled (+1s) inside)
        $eventDispatcher->dispatch('activating.event', $event);
        self::assertEquals('active', $subject->getStatus());

        // sleep for a while (wait 2 sec to avoid "the same second" collision)
        // and run scheduler with 1-sec runtime to check that sleeping transition is executed
        sleep(2);
        $this->runScheduler(1);
        $clientEm->refresh($subject);
        self::assertEquals('sleeping', $subject->getStatus());

        $eventDispatcher->dispatch('prolong.event', $event);

        // sleep for a while (in order to execute scheduler in the time when closing transition should be applied without prolong.event fired)
        // and run scheduler with 1-sec runtime to check that closed transition is not executed due to prolongation
        usleep(500000);
        $this->runScheduler(1);
        $clientEm->refresh($subject);
        self::assertEquals('sleeping', $subject->getStatus());

        // sleep for a while (some time we waste during waiting that scheduler finish his work so no need to wait so long)
        // and run scheduler with 1-sec runtime to check that closed transition is executed
        $this->runScheduler(1);
        $clientEm->refresh($subject);
        self::assertEquals('closed', $subject->getStatus());
    }

    protected function runScheduler($runtime = 1)
    {
        $schedulerCommand = new RunCommand();
        $this->runConsoleCommand(
            $schedulerCommand,
            [
                '--max-runtime' => $runtime,
                // set worker name explicitly in order to avoid errors caused by 50 characters name restrictions on
                // testing envs like travis
                '--worker-name' => uniqid("worker_", true)
            ]
        );
    }

    private function runConsoleCommand(Command $command, array $params = []): int
    {
        $command->setApplication($this->app);
        // use CommandTester to simple command running
        $commandRunner = new CommandTester($command);
        return $commandRunner->execute($params);
    }
}
