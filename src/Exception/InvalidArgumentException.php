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

namespace Gtt\Bundle\WorkflowExtensionsBundle\Exception;

/**
 * Bundle-level invalid argument exception
 */
class InvalidArgumentException extends \InvalidArgumentException implements WorkflowExceptionInterface
{

}
