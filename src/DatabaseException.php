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
     * construct()
     *
     * @param string     $message  The exception message.
     * @param array      $errors   The validation errors.
     * @param integer    $code     The exception code.
     * @param \Throwable $previous The previous exception.
     *
     * @return void
     */
    public function __construct(string $message = '', array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        $errorMsg = $message;

        // Extract the error code
        preg_match('/SQLSTATE\[\w+\]: (.+)/', $errorMsg, $matches);
        $databaseName = config('database.database');
        $message      = str_after($matches[1], ': ') ?? 'Unknown database error';
        $message      = preg_replace('/^\d+/', '', trim($message));
        $message      = str_replace($databaseName . '.', '', $message);

        parent::__construct($message, $errors, $code, $previous);
    }

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
