<?php
declare(strict_types=1);

namespace CorianderCore\Core\Support;

final class OutputBuffer
{
    /**
     * Capture output emitted while running user code.
     *
     * @param callable():mixed $callback
     * @return array{0:mixed,1:string}
     */
    public static function capture(callable $callback): array
    {
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $result = $callback();
            while (ob_get_level() > $bufferLevel + 1) {
                ob_end_flush();
            }
            if (ob_get_level() !== $bufferLevel + 1) {
                throw new \RuntimeException('Application code closed the framework output buffer');
            }
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
