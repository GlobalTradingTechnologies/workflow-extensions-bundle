<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 02.08.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\MarkingStore;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

/**
 * Decorates original marking store with ORM persisting feature during mutating the subject marking
 */
class OrmPersistentMarkingStore implements MarkingStoreInterface
{
    /**
     * Origin marking store
     *
     * @var MarkingStoreInterface
     */
    private $originMarkingStore;

    /**
     * Doctrine registry
     *
     * @var Registry
     */
    private $doctrineRegistry;

    /**
     * OrmPersistentMarkingStore constructor.
     *
     * @param MarkingStoreInterface $originMarkingStore origin marking store
     * @param Registry              $doctrineRegistry   doctrine registry
     */
    public function __construct(MarkingStoreInterface $originMarkingStore, Registry $doctrineRegistry)
    {
        $this->doctrineRegistry   = $doctrineRegistry;
        $this->originMarkingStore = $originMarkingStore;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking($subject)
    {
        return $this->originMarkingStore->getMarking($subject);
    }

    /**
     * Updates subject's marking and persists it using ORM
     *
     * {@inheritdoc}
     */
    public function setMarking($subject, Marking $marking)
    {
        $this->originMarkingStore->setMarking($subject, $marking);

        $manager = $this->doctrineRegistry->getManagerForClass(get_class($subject));
        // for DEFERRED_EXPLICIT change tracking policies we also persisting subject here
        $manager->persist($subject);
        $manager->flush();
    }
}
