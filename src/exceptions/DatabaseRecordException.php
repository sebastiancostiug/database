<?php
/**
 * @package     Database
 *
 * @subpackage  DatabaseRecordException
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
 * DatabaseRecordException class
 */
class DatabaseRecordException extends \Exception
{
    /**
     * @var array $errorInfo The errors that occurred during the Record.
     */
    private array $_errorInfo;

    /**
     * DatabaseRecordException constructor.
     *
     * @param string          $message   The exception message.
     * @param array           $errorInfo The PDO errors that occurred during the record interaction.
     * @param integer         $code      The exception code.
     * @param \Throwable|null $previous  The previous exception used for the exception chaining.
     *
     * @return void
     */
    public function __construct(string $message, array $errorInfo = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message);
        $this->_errorInfo = $errorInfo;
    }

    /**
     * Get the errors that occurred during the Record.
     *
     * @return array The errors that occurred during the record interaction.
     */
    public function getErrorInfo(): array
    {
        return $this->_errorInfo;
    }
}
