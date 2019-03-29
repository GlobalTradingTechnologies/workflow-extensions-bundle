<?php
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Functional\Configuration\ScheduleCase\Fixtures\ClientBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Transfer
 *
 * @ORM\Table(name="client")
 * @ORM\Entity
 */
class Client
{
    /**
     * @var integer
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    public function __construct()
    {
        $this->status = "inactive";
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Client
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets status marking
     *
     * @param $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }
}
