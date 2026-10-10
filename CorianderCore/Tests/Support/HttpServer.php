<?php
declare(strict_types=1);
namespace CorianderCore\Tests\Support;

/** A real HTTP fixture, independent of curl and platform shell quoting. */
final class HttpServer
{
    private $process;
    private string $address;
    private string $log;

    public function __construct(string $root, string $router, array $environment = [])
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
        if ($socket === false) {
            throw new \RuntimeException($message);
        }
        $this->address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->log = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_server_' . bin2hex(random_bytes(5)) . '.log';
        $this->process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-S', $this->address, '-t', $root, $router],
            [0 => ['pipe', 'r'], 1 => ['file', $this->log, 'a'], 2 => ['file', $this->log, 'a']], $pipes, PROJECT_ROOT, $environment === [] ? null : array_merge(getenv(), $environment), ['bypass_shell' => true]);
        if (!is_resource($this->process)) {
            throw new \RuntimeException('Cannot start HTTP server');
        }
        fclose($pipes[0]);
        for ($i = 0; $i < 50; $i++) {
            $connection = @stream_socket_client('tcp://' . $this->address, $error, $message, 0.1);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            if (!proc_get_status($this->process)['running']) {
                break;
            }
            usleep(100_000);
        }
        $failure = @file_get_contents($this->log);
        $this->stop();
        throw new \RuntimeException('HTTP fixture did not start: ' . $failure);
    }

    public function request(string $method, string $path, string $body = '', array $headers = []): array
    {
        $socket = stream_socket_client('tcp://' . $this->address, $error, $message, 5);
        if ($socket === false) {
            throw new \RuntimeException($message);
        }
        stream_set_timeout($socket, 5);
        $wire = $method . ' ' . $path . " HTTP/1.0\r\nHost: " . $this->address . "\r\nConnection: close\r\nContent-Length: " . strlen($body) . "\r\n";
        foreach ($headers as $name => $value) {
            $wire .= $name . ': ' . $value . "\r\n";
        }
        $wire .= "\r\n" . $body;
        while ($wire !== '') {
            $written = fwrite($socket, $wire);
            if ($written === false || $written === 0) {
                fclose($socket);
                throw new \RuntimeException('HTTP write failed');
            }
            $wire = substr($wire, $written);
        }
        $raw = stream_get_contents($socket);
        $timedOut = stream_get_meta_data($socket)['timed_out'];
        fclose($socket);
        if ($timedOut || $raw === false || !str_contains($raw, "\r\n\r\n")) {
            throw new \RuntimeException('Incomplete HTTP response');
        }
        [$head, $body] = explode("\r\n\r\n", $raw, 2);
        $lines = explode("\r\n", $head);
        preg_match('#^HTTP/\S+ (\d+)#', array_shift($lines), $status);
        $headers = [];
        foreach ($lines as $line) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower($name)][] = trim($value);
        }
        return ['status' => (int) $status[1], 'headers' => $headers, 'body' => $body];
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->process = null;
        if (is_file($this->log)) {
            unlink($this->log);
        }
    }

    public function __destruct() { $this->stop(); }
}
