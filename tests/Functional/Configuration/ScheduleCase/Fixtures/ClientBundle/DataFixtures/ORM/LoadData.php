<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 17.07.15
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures\ClientBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures\ClientBundle\Entity\Client;

class LoadData implements FixtureInterface
{
    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $client = new Client();
        $client->setName("Johnny");

        $manager->persist($client);

        $manager->flush();
    }
}
