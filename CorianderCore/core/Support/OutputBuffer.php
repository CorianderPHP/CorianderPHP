<?php
declare(strict_types=1);

namespace CorianderCore\Core\Support;

final class OutputBuffer
{
    /**
     * Capture output emitted while running user code.
     *
     * @return array{0:mixed,1:string}
     */
    public static function capture(callable $callback): array
    {
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $result = $callback();
            $content = (string) ob_get_clean();

            return [$result, $content];
        } catch (\Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }
    }
}
