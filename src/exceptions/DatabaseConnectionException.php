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

namespace database\exceptions;

/**
 * DatabaseConnectionException class
 */
class DatabaseConnectionException extends \Exception
{
    /**
     * @var array $debugInfo The errors that occurred during the connection.
     */
    private array $_debugInfo;

    /**
     * DatabaseConnectionException constructor.
     *
     * @param string          $message   The exception message.
     * @param array           $debugInfo The debug info for the error that occurred during the connection.
     * @param integer         $code      The exception code.
     * @param \Throwable|null $previous  The previous exception used for the exception chaining.
     *
     * @return void
     */
    public function __construct(string $message, array $debugInfo = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message);
        $this->_debugInfo = $debugInfo;
    }

    /**
     * Get the errors that occurred during the connection.
     *
     * @return array The errors that occurred during the connection.
     */
    public function getDebugInfo(): array
    {
        return $this->_debugInfo;
    }
}
