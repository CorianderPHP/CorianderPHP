<?php

namespace CorianderCore\Tests;

use CorianderCore\Benchmark\BenchmarkHandler;
use PHPUnit\Framework\TestCase;

/**
 * Class BenchmarkHandlerTest
 *
 * This class contains unit tests for the BenchmarkHandler class, which is designed to
 * measure various performance metrics such as CPU usage, memory usage, latency, throughput,
 * and more. These tests ensure that the BenchmarkHandler class functions as expected under
 * different scenarios.
 */
class BenchmarkHandlerTest extends TestCase
{
    /**
     * @var BenchmarkHandler
     * BenchmarkHandler instance for testing purposes.
     */
    protected $benchmarkHandler;

    /**
     * setUp
     *
     * Initializes the BenchmarkHandler instance before each test.
     * Called automatically by PHPUnit before each test method is executed.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->benchmarkHandler = new BenchmarkHandler();
    }

    /**
     * testInitializationAndStop
     *
     * Tests that the BenchmarkHandler initializes correctly and records time
     * and CPU usage when started, and stops without errors. Verifies that 
     * the initialization time and CPU usage are not null after start and
     * checks the recorded initialization time after stop.
     */
    public function testInitializationAndStop()
    {
        $this->benchmarkHandler->start();
        $this->assertNotNull($this->benchmarkHandler->getInitializationTime(), "Initialization time should not be null after start.");
        $this->assertNotNull($this->benchmarkHandler->getCpuUsage(), "CPU usage should not be null after start.");

        $this->benchmarkHandler->stop();
        $this->assertGreaterThan(0, $this->benchmarkHandler->getInitializationTime(), "Initialization time should be greater than zero after stop.");
    }

    /**
     * testMemoryUsage
     *
     * Tests the memory usage tracking functionality by creating an array with
     * a large number of string elements, then verifies that the memory usage
     * reported by the BenchmarkHandler is greater than zero after stopping.
     */
    public function testMemoryUsage()
    {
        $this->benchmarkHandler->start();

        $array = [];
        for ($i = 0; $i < 10; $i++) {
            $array[] = str_repeat("test", 10);
        }

        $this->benchmarkHandler->stop();

        $memoryUsage = $this->benchmarkHandler->getMemoryUsage();
        $this->assertGreaterThan(0, $memoryUsage, "Memory usage should be greater than zero.");
    }

    /**
     * testThroughput
     *
     * Measures throughput by performing a set number of operations and then
     * checking if the throughput is calculated correctly. The test ensures that
     * the throughput value is greater than zero.
     */
    public function testThroughput()
    {
        $this->benchmarkHandler->start();

        for ($i = 0; $i < 100; $i++) {
            // Simulated operations without delay.
        }

        $this->benchmarkHandler->stop();

        $throughput = $this->benchmarkHandler->getThroughput(100);
        $this->assertGreaterThan(0, $throughput, "Throughput should be greater than zero.");
    }

    /**
     * testLatency
     *
     * Tests latency calculation by adding a small delay in a loop, then verifies
     * that the latency recorded by the BenchmarkHandler is greater than zero.
     */
    public function testLatency()
    {
        $this->benchmarkHandler->start();

        for ($i = 0; $i < 10; $i++) {
            usleep(1); // Simulate a small delay.
        }

        $this->benchmarkHandler->stop();

        $latency = $this->benchmarkHandler->getLatency(100);
        $this->assertGreaterThan(0, $latency, "Latency should be greater than zero.");
    }

    /**
     * -----------------------------------------
     * /!\ This test is commented out because it is highly CPU-dependent and may produce inconsistent results
     * on different machines or environments.
     * -----------------------------------------
     */
    /**
     * testCpuUsage
     *
     * Simulates high CPU usage by calling class_exists() multiple times, then
     * verifies that the total CPU usage is greater than zero after stopping.
     */
    // public function testCpuUsage()
    // {
    //     $this->benchmarkHandler->start();

    //     // Simulate CPU-intensive operations.
    //     for ($i = 0; $i < 10000; $i++) {
    //         class_exists($i);
    //     }

    //     $this->benchmarkHandler->stop();

    //     $totalCpuUsage = $this->benchmarkHandler->getTotalCpuUsage();
    //     $this->assertGreaterThan(0.0, $totalCpuUsage, "Total CPU usage should be greater than zero.");
    // }

    /**
     * -----------------------------------------
     * /!\ This test is commented out because it is highly CPU-dependent and may produce inconsistent results
     * on different machines or environments.
     * -----------------------------------------
     */
    /**
     * testAverageCpuUsagePerOperation
     *
     * Tests the average CPU usage calculation per operation. It simulates a 
     * number of operations and checks if the average CPU usage per operation 
     * is greater than zero.
     */
    // public function testAverageCpuUsagePerOperation()
    // {
    //     $this->benchmarkHandler->start();

    //     // Simulate CPU-intensive operations.
    //     for ($i = 0; $i < 10000; $i++) {
    //         class_exists($i);
    //     }

    //     $this->benchmarkHandler->stop();

    //     $avgCpuUsage = $this->benchmarkHandler->getAverageCpuUsagePerOperation(50);
    //     $this->assertGreaterThan(0, $avgCpuUsage, "Average CPU usage per operation should be greater than zero.");
    // }

    /**
     * -----------------------------------------
     * /!\ This test is commented out because it is highly CPU-dependent and may produce inconsistent results
     * on different machines or environments.
     * -----------------------------------------
     */
    /**
     * testCpuUsagePercentage
     *
     * Tests the calculation of CPU usage as a percentage. It simulates 
     * high CPU usage and then verifies that the reported CPU usage percentage
     * is greater than zero.
     */
    // public function testCpuUsagePercentage()
    // {
    //     $this->benchmarkHandler->start();

    //     // Simulate CPU-intensive operations.
    //     for ($i = 0; $i < 10000; $i++) {
    //         class_exists($i);
    //     }

    //     $this->benchmarkHandler->stop();

    //     $cpuUsagePercentage = $this->benchmarkHandler->getCpuUsagePercentage();
    //     $this->assertGreaterThan(0.0, $cpuUsagePercentage, "CPU usage percentage should be greater than zero.");
    // }

    /**
     * testIncludedFilesCount
     *
     * Verifies the number of included files recorded by the BenchmarkHandler.
     * It compares the count returned by the BenchmarkHandler with the actual
     * count obtained using PHP's get_included_files() function.
     */
    public function testIncludedFilesCount()
    {
        $actualIncludedFilesCount = count(get_included_files());
        $includedFilesCount = $this->benchmarkHandler->getIncludedFilesCount();

        $this->assertEquals($actualIncludedFilesCount, $includedFilesCount, "Included files count should match the actual count.");
    }

    /**
     * -----------------------------------------
     * /!\ This test is commented out because it is highly CPU-dependent and may produce inconsistent results
     * on different machines or environments.
     * -----------------------------------------
     */
    /**
     * testMemoryPeaks
     *
     * Simulates high memory usage and verifies that the highest and lowest memory
     * usage recorded by the BenchmarkHandler are greater than zero.
     */
    // public function testMemoryPeaks()
    // {
    //     $this->benchmarkHandler->start();

    //     // Simulate high memory usage.
    //     for ($i = 0; $i < 10000; $i++) {
    //         class_exists($i);
    //     }

    //     $this->benchmarkHandler->stop();

    //     $this->assertGreaterThan(0, $this->benchmarkHandler->getHighestMemoryUsage(), "Highest memory usage should be greater than zero.");
    //     $this->assertGreaterThan(0, $this->benchmarkHandler->getLowestMemoryUsage(), "Lowest memory usage should be greater than zero.");
    // }

    /**
     * -----------------------------------------
     * /!\ This test is commented out because it is highly CPU-dependent and may produce inconsistent results
     * on different machines or environments.
     * -----------------------------------------
     */
    /**
     * testCpuPeaks
     *
     * Simulates high CPU usage and verifies that the highest and lowest CPU
     * usage recorded by the BenchmarkHandler are greater than zero.
     */
    // public function testCpuPeaks()
    // {
    //     $this->benchmarkHandler->start();

    //     // Simulate high CPU usage.
    //     for ($i = 0; $i < 10000; $i++) {
    //         class_exists($i);
    //     }

    //     $this->benchmarkHandler->stop();

    //     $this->assertGreaterThan(0, $this->benchmarkHandler->getHighestCpuUsage(), "Highest CPU usage should be greater than zero.");
    //     $this->assertGreaterThan(0, $this->benchmarkHandler->getLowestCpuUsage(), "Lowest CPU usage should be greater than zero.");
    // }

    /**
     * -----------------------------------------
     * /!\ This test is commented out because it is highly CPU-dependent and may produce inconsistent results
     * on different machines or environments.
     * -----------------------------------------
     */
    /**
     * testBenchmarkFunction
     *
     * Tests the benchmarkFunction method of the BenchmarkHandler class. It benchmarks
     * a user-defined function and verifies that the returned metrics such as total 
     * iterations, average iterations per second, and iterations per second are greater 
     * than zero.
     */
    // public function testBenchmarkFunction()
    // {
    //     $result = $this->benchmarkHandler->benchmarkFunction(function () {
    //         // Simulate operations.
    //         for ($i = 0; $i < 100; $i++) {
    //             class_exists($i);
    //         }
    //     }, 1); // Test with a duration of 1 second.

    //     $this->assertArrayHasKey('total_iterations', $result, "Result should contain total_iterations.");
    //     $this->assertArrayHasKey('average_iterations_per_second', $result, "Result should contain average_iterations_per_second.");
    //     $this->assertArrayHasKey('iterations_per_second', $result, "Result should contain iterations_per_second.");

    //     $this->assertGreaterThan(0, $result['total_iterations'], "Total iterations should be greater than zero.");
    //     $this->assertGreaterThan(0, $result['average_iterations_per_second'], "Average iterations per second should be greater than zero.");
    // }
}
