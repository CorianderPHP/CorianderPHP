<?php
declare(strict_types=1);

/*
 * BenchmarkHandler captures execution metrics to evaluate performance of
 * framework components such as memory, CPU and initialization time.
 */

namespace CorianderCore\Core\Benchmark;

/**
 * BenchmarkHandler is responsible for measuring key performance indicators
 * such as initialization speed, memory usage, throughput, latency, CPU usage, and file inclusion tracking.
 */
class BenchmarkHandler
{
    /**
     * @var float Start time of the benchmark.
     */
    protected float $startTime = 0.0;

    /**
     * @var float End time of the benchmark.
     */
    protected float $endTime = 0.0;

    /**
     * @var int Initial memory usage in bytes.
     */
    protected int $initialMemory = 0;

    /**
     * @var float CPU usage at start.
     */
    protected float $cpuStart = 0.0;

    /**
     * @var float CPU usage at end.
     */
    protected float $cpuEnd = 0.0;

    /**
     * @var int Lowest memory usage recorded.
     */
    protected int $lowestMemoryUsage = 0;

    /**
     * @var int Highest memory usage recorded.
     */
    protected int $highestMemoryUsage = 0;

    /**
     * @var float Lowest CPU usage recorded.
     */
    protected float $lowestCpuUsage = 0.0;

    /**
     * @var float Highest CPU usage recorded.
     */
    protected float $highestCpuUsage = 0.0;

    /**
     * Initializes the BenchmarkHandler by recording the start time, memory usage, and CPU usage.
     * Also initializes the peak and low values for memory and CPU usage.
     */
    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->initialMemory = memory_get_usage();
        $this->cpuStart = $this->getCpuUsage();

        // Initialize peak and low values with the starting memory and CPU usage
        $this->lowestMemoryUsage = memory_get_usage();
        $this->highestMemoryUsage = $this->lowestMemoryUsage;

        $this->lowestCpuUsage = $this->cpuStart;
        $this->highestCpuUsage = $this->cpuStart;
    }

    /**
     * Stops the benchmark by recording the end time and CPU usage.
     * Also updates the peak and low values for memory and CPU usage.
     */
    public function stop(): void
    {
        $this->endTime = microtime(true);
        $this->cpuEnd = $this->getCpuUsage();

        // Update the final memory and CPU usage to track the highest peaks
        $this->updateMemoryPeak();
        $this->updateCpuPeak();
    }

    /**
     * Calculates and returns the elapsed time between start and stop.
     *
     * @return float Time taken in seconds.
     */
    public function getInitializationTime(): float
    {
        $end = $this->endTime ?: microtime(true);
        return $end - $this->startTime;
    }

    /**
     * Returns the peak memory usage during the execution.
     *
     * @return int Peak memory usage in bytes.
     */
    public function getMemoryUsage(): int
    {
        return memory_get_peak_usage() - $this->initialMemory;
    }

    /**
     * Measures throughput by calculating requests or operations per second.
     *
     * @param int $operations The number of operations or requests handled during the benchmark period.
     * @return float The number of operations per second.
     */
    public function getThroughput(int $operations): float
    {
        return $operations / $this->getInitializationTime();
    }

    /**
     * Measures latency by returning the average time per operation.
     *
     * @param int $operations The number of operations or requests handled during the benchmark period.
     * @return float Average latency per operation in seconds.
     */
    public function getLatency(int $operations): float
    {
        return $this->getInitializationTime() / $operations;
    }

    /**
     * Gets the current CPU usage by the script in seconds (user + system time).
     *
     * @return float CPU time in seconds.
     */
    public function getCpuUsage(): float
    {
        $data = getrusage();
        return ($data["ru_utime.tv_sec"] + $data["ru_utime.tv_usec"] / 1e6) +
            ($data["ru_stime.tv_sec"] + $data["ru_stime.tv_usec"] / 1e6);
    }

    /**
     * Gets the total CPU usage over the benchmark period.
     *
     * @return float CPU usage during the benchmark period in seconds.
     */
    public function getTotalCpuUsage(): float
    {
        return $this->cpuEnd - $this->cpuStart;
    }

    /**
     * Gets the average CPU usage per operation during the benchmark period.
     *
     * @param int $operations The number of operations performed during the benchmark period.
     * @return float Average CPU usage per operation in seconds.
     */
    public function getAverageCpuUsagePerOperation(int $operations): float
    {
        return $this->getTotalCpuUsage() / $operations;
    }

    /**
     * Gets the number of CPU cores available.
     *
     * @return int Number of CPU cores.
     */
    public function getCpuCores(): int
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows command to get the number of CPU cores
            return (int)shell_exec('wmic cpu get NumberOfCores | findstr /r /r "[0-9]"');
        }
        return (int)shell_exec('nproc');
    }

    /**
     * Calculates the CPU usage percentage over the benchmark period.
     *
     * @return float CPU usage as a percentage.
     */
    public function getCpuUsagePercentage(): float
    {
        // Get initialization time and number of CPU cores
        $initializationTime = $this->getInitializationTime();
        $cpuCores = $this->getCpuCores();

        // Check if either initializationTime or cpuCores is zero to avoid division by zero
        if ($initializationTime == 0 || $cpuCores == 0) {
            return 0.0; // Return 0% CPU usage in case of zero total available CPU time
        }

        // Total available CPU time is wall-clock time multiplied by the number of cores
        $totalCpuTime = $initializationTime * $cpuCores;

        // Used CPU time
        $usedCpuTime = $this->getTotalCpuUsage();

        // Calculate percentage of CPU time used
        return ($usedCpuTime / $totalCpuTime) * 100;
    }


    /**
     * Counts the number of files included or required during the script execution.
     *
     * @return int The number of included or required files.
     */
    public function getIncludedFilesCount(): int
    {
        return count(get_included_files());
    }

    /**
     * Tracks the lowest and highest memory usage during the benchmarking period.
     */
    public function updateMemoryPeak(): void
    {
        $currentMemoryUsage = memory_get_usage();

        if ($currentMemoryUsage < $this->lowestMemoryUsage) {
            $this->lowestMemoryUsage = $currentMemoryUsage;
        }

        if ($currentMemoryUsage > $this->highestMemoryUsage) {
            $this->highestMemoryUsage = $currentMemoryUsage;
        }
    }

    /**
     * Tracks the lowest and highest CPU usage during the benchmarking period.
     */
    public function updateCpuPeak(): void
    {
        $currentCpuUsage = $this->getCpuUsage();

        if ($currentCpuUsage < $this->lowestCpuUsage) {
            $this->lowestCpuUsage = $currentCpuUsage;
        }

        if ($currentCpuUsage > $this->highestCpuUsage) {
            $this->highestCpuUsage = $currentCpuUsage;
        }
    }

    /**
     * Gets the lowest memory usage recorded during the benchmark.
     *
     * @return int Lowest memory usage in bytes.
     */
    public function getLowestMemoryUsage(): int
    {
        return $this->lowestMemoryUsage;
    }

    /**
     * Gets the highest memory usage recorded during the benchmark.
     *
     * @return int Highest memory usage in bytes.
     */
    public function getHighestMemoryUsage(): int
    {
        return $this->highestMemoryUsage;
    }

    /**
     * Gets the lowest CPU usage recorded during the benchmark.
     *
     * @return float Lowest CPU usage in seconds.
     */
    public function getLowestCpuUsage(): float
    {
        return $this->lowestCpuUsage;
    }

    /**
     * Gets the highest CPU usage recorded during the benchmark.
     *
     * @return float Highest CPU usage in seconds.
     */
    public function getHighestCpuUsage(): float
    {
        return $this->highestCpuUsage;
    }

    /**
     * Benchmarks a given function for a specified duration.
     *
     * @param callable $function The function to benchmark.
     * @param int $duration The duration to run the benchmark in seconds.
     * @return array An array containing benchmark results: total iterations, average iterations per second, and iterations per second.
     */
    public function benchmarkFunction(callable $function, int $duration): array
    {
        // Initialize benchmark
        $this->start();

        $startTime = microtime(true);
        $iterations = 0;
        $iterationsPerSecond = [];

        // Run the benchmark for the specified duration
        while ((microtime(true) - $startTime) < $duration) {
            $function(); // Call the function being benchmarked

            $iterations++;

            // Track iterations per second
            $currentSecond = (int)(microtime(true) - $startTime);
            if ($currentSecond < $duration) { // Ensure we don't log beyond the specified duration
                if (!isset($iterationsPerSecond[$currentSecond])) {
                    $iterationsPerSecond[$currentSecond] = 0;
                }
                $iterationsPerSecond[$currentSecond]++;
            }

            // Update memory and CPU peaks during each iteration
            $this->updateMemoryPeak();
            $this->updateCpuPeak();
        }

        // Stop the benchmark
        $this->stop();

        // Calculate average iterations per second
        $averageIterationsPerSecond = $iterations / $duration;

        // Return benchmark results
        return [
            'total_iterations' => $iterations,
            'average_iterations_per_second' => $averageIterationsPerSecond,
            'iterations_per_second' => $iterationsPerSecond,
            'lowest_memory_usage' => $this->getLowestMemoryUsage(),
            'highest_memory_usage' => $this->getHighestMemoryUsage(),
            'lowest_cpu_usage' => $this->getLowestCpuUsage(),
            'highest_cpu_usage' => $this->getHighestCpuUsage(),
            'total_cpu_usage' => $this->getTotalCpuUsage(),
            'average_cpu_usage_per_iteration' => $this->getAverageCpuUsagePerOperation($iterations),
            'total_memory_usage' => $this->getMemoryUsage(),
            'total_included_files' => $this->getIncludedFilesCount(),
            'total_execution_time' => $this->getInitializationTime(),
            'cpu_usage_percentage' => $this->getCpuUsagePercentage(),
        ];
    }
}
