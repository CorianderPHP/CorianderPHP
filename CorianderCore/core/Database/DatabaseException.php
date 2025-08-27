<?php
declare(strict_types=1);

/*
 * DatabaseException standardises error reporting for database operations
 * within the framework.
 */

/**
 * Exception thrown when a database operation fails within the framework.
 *
 * Wraps lower-level PDO exceptions to provide context-aware error messages.
 */
namespace CorianderCore\Core\Database;

use RuntimeException;

class DatabaseException extends RuntimeException
{
}
