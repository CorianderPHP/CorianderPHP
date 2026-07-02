<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console;

final class CommandExitCode
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID_USAGE = 2;
    public const UNKNOWN_COMMAND = 3;
}
