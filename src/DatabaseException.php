<?php
/**
 * @package     Database
 *
 * @subpackage  DatabaseConnectionException
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2019-2024 Sebastian Costiug
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    exceptions
 *
 * @since       2024-02-05
 */

namespace database;

use common\Exception;

/**
 * DatabaseConnectionException class
 */
class DatabaseException extends Exception
{
    /**
     * Get the exception name.
     *
     * @return string The exception name.
     */
    public function getName(): string
    {
        return 'Database exception';
    }
}
