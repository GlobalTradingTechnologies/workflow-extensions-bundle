<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 22.09.16
 */
declare(strict_types=1);

namespace Gtt\Bundle\WorkflowExtensionsBundle\Utils;

/**
 * Service array utils
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class ArrayUtils
{
    /**
     * Recursively checks that passed array is associative or not
     *
     * @param array $array          input array
     * @param bool  $recursiveCheck whether to perform recursive check or not
     *
     * @return bool
     */
    public static function isArrayAssoc(array $array, bool $recursiveCheck = true): bool
    {
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                return true;
            }
            if ($recursiveCheck && is_array($value) && static::isArrayAssoc($value)) {
                return true;
            }
        }

        return false;
    }
}
