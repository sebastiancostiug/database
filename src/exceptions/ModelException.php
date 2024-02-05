<?php
/**
 * @package     Database
 *
 * @subpackage  ModelException
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
 * ModelException class
 */
class ModelException extends \Exception
{
    /**
     * @var array $errors The errors that occurred during the Model.
     */
    private array $_errors;

    /**
     * ModelException constructor.
     *
     * @param string          $message  The exception message.
     * @param array           $errors   The Model errors that occurred during the Model interaction.
     * @param integer         $code     The exception code.
     * @param \Throwable|null $previous The previous exception used for the exception chaining.
     *
     * @return void
     */
    public function __construct(string $message, array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message);
        $this->_errors = $errors;
    }

    /**
     * Get the errors that occurred during the Model.
     *
     * @return array The errors that occurred during the Model.
     */
    public function getErrors(): array
    {
        return $this->_errors;
    }
}
