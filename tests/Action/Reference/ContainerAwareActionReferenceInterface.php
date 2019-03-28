<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 31.10.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Interface for testing action references implementing the both of ContainerAwareInterface and ActionReferenceInterface
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
interface ContainerAwareActionReferenceInterface extends ActionReferenceInterface, ContainerAwareInterface
{

}
