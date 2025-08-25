<?php

namespace CorianderCore\Core\Console\Commands\Benchmark;

use CorianderCore\Core\Benchmark\BenchmarkHandler;
use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Router\NameFormatter;

/**
 * BenchmarkRouter handles benchmarking related to routing performance.
 * It simulates routing requests and measures the performance of the router.
 */
class BenchmarkRouter
{
    /**
     * Executes the benchmark process for the router.
     *
     * @param array $args The arguments passed to the benchmark:router command (e.g., route name, duration).
     */
    public function execute(array $args)
    {
        // Default route to 'home' if not specified
        $route = $args[0] ?? 'home';

        // Default duration to 1 second if not specified
        $duration = isset($args[1]) ? (int)$args[1] : 1;

        // Initialize the Router
        $router = Router::getInstance();

        // Verify if the route exists
        if (!$this->routeExists($router, $route)) {
            ConsoleOutput::print("&4[Error]&7 Route '{$route}' does not exist.");
            return;
        }

        // Initialize BenchmarkHandler
        $benchmark = new BenchmarkHandler();

        // Define the routing function to be benchmarked
        $routingFunction = function() use ($router, $route) {
            $_SERVER['REQUEST_URI'] = "/{$route}";
            $_SERVER['REQUEST_METHOD'] = 'GET';
            ob_start();
            $router->dispatch(); // Dispatch the router for each iteration
            ob_end_clean(); // Clear the buffer to avoid output
        };

        // Run the benchmark using the benchmarkFunction method
        $results = $benchmark->benchmarkFunction($routingFunction, $duration);

        // Output the results
        ConsoleOutput::print("&uBenchmark Results for Route: {$route} ({$duration} seconds)");
        ConsoleOutput::print("&8Execution Time: " . formatTime($results['total_execution_time']));
        ConsoleOutput::hr();

        // Display results for each second
        foreach ($results['iterations_per_second'] as $second => $count) {
            ConsoleOutput::print("&8Iterations completed in second {$second}:&7 {$count}");
        }

        // Summary results
        ConsoleOutput::hr();
        ConsoleOutput::print("Total Iterations: &2{$results['total_iterations']}");
        ConsoleOutput::print("Average Iterations per Second: &e{$results['average_iterations_per_second']}");
        ConsoleOutput::print("Total Included Files: &3" . $results['total_included_files']);
        ConsoleOutput::hr();
        ConsoleOutput::print("Lowest Memory Usage: &7" . $results['lowest_memory_usage'] . " bytes");
        ConsoleOutput::print("Highest Memory Usage: &7" . $results['highest_memory_usage'] . " bytes");
        ConsoleOutput::print("Total Memory Usage: &7" . $results['total_memory_usage'] . " bytes");
        ConsoleOutput::hr();
        ConsoleOutput::print("Lowest CPU Usage: &7" . formatTime($results['lowest_cpu_usage']));
        ConsoleOutput::print("Highest CPU Usage: &7" . formatTime($results['highest_cpu_usage']));
        ConsoleOutput::print("Average CPU Usage per Iteration: &7" . formatTime($results['average_cpu_usage_per_iteration']));
        ConsoleOutput::print("Total CPU Usage: &7" . formatTime($results['total_cpu_usage']));
        ConsoleOutput::print("Total CPU Usage: &7" . round($results['cpu_usage_percentage'], 3) . " %");
    }

    /**
     * Check if the specified route exists in the router.
     *
     * @param Router $router The router instance to check against.
     * @param string $route The route name to check.
     * @return bool True if the route exists, false otherwise.
     */
    private function routeExists(Router $router, string $route): bool
    {
        // Check for manually added custom routes
        if (isset($router->routes[$route])) {
            return true;
        }

        // Check for a controller matching the route name
        $controllerName = NameFormatter::toPascalCase($route) . 'Controller';
        $controllerClass = 'Controllers\\' . $controllerName;

        if (class_exists($controllerClass)) {
            return true;
        }

        // Check if a view for the route exists
        $viewPath = PROJECT_ROOT . "/public/public_views/{$route}/index.php";
        return file_exists($viewPath);
    }
}

/**
 * Formats a time value in seconds to a more readable unit (µs, ms, s).
 *
 * @param float $timeInSeconds The time value in seconds.
 * @return string Formatted time with the appropriate unit.
 */
function formatTime(float $timeInSeconds): string
{
    // Convert time to microseconds (µs)
    $timeInMicroseconds = $timeInSeconds * 1e6;
    
    if ($timeInMicroseconds < 1000) {
        // If less than 1 ms, display in µs
        return round($timeInMicroseconds, 2) . " µs";
    } elseif ($timeInMicroseconds < 1e6) {
        // If less than 1 second, display in ms
        return round($timeInMicroseconds / 1000, 2) . " ms";
    } else {
        // If 1 second or more, display in seconds
        return round($timeInSeconds, 2) . " s";
    }
}
