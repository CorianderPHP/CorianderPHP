<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Make\Database;

use CorianderCore\Core\Console\Commands\Make\Database\MySQL\MakeMySQL;
use CorianderCore\Core\Console\Commands\Make\Database\SQLite\MakeSQLite;
use CorianderCore\Core\Console\Services\PdoDriverService;
use CorianderCore\Core\Console\ConsoleOutput;

/**
 * The MakeDatabase class is responsible for directing the user to either the MySQL or SQLite setup process.
 * It checks if the necessary PDO drivers for MySQL are available before allowing the user to choose.
 */
class MakeDatabase
{
    /**
     * @var PdoDriverService
     */
    protected PdoDriverService $pdoDriverService;

    /**
     * Constructor for MakeDatabase.
     *
     * @param PdoDriverService|null $pdoDriverService
     */
    public function __construct(?PdoDriverService $pdoDriverService = null)
    {
        // If no PdoDriverService is passed, instantiate a default one
        $this->pdoDriverService = $pdoDriverService ?? new PdoDriverService();
    }

    /**
     * Executes the database configuration process based on user input.
     * Checks for the necessary PDO drivers before prompting the user to choose between MySQL and SQLite.
     */
    public function execute(): void
    {
        // Check available PDO drivers and warn if MySQL driver is missing
        $availableDrivers = $this->pdoDriverService->getAvailableDrivers();
        $mysqlAvailable = in_array('mysql', $availableDrivers);
        $iniPath = php_ini_loaded_file();  // Get the currently loaded php.ini file

        if (!$mysqlAvailable) {
            ConsoleOutput::print("&e| [Warning]&r&7 &uMySQL PDO driver is not available&r&7.");
            ConsoleOutput::print("&e| &7You will not be able to create a MySQL database.\n&e|");
            ConsoleOutput::print("&e| &7To install the MySQL PDO driver, refer to the following:");
            ConsoleOutput::print("&e| &7- &uFor Linux&r&7: sudo apt-get install php-mysql");
            ConsoleOutput::print("&e| &7- &uFor Windows&r&7: enable '&lextension=pdo_mysql&r&7' in your php.ini file.");
            ConsoleOutput::print("&e| &7  The loaded php.ini file is located at: &l$iniPath&r&7");
            ConsoleOutput::hr();
        }

        do {
            // Display driver availability and guide the user
            ConsoleOutput::print("Please choose the type of database:");
            ConsoleOutput::print("&l0&r&7. Exit");

            // Always display MySQL option, but if the driver is missing, display it in red and give instructions
            if ($mysqlAvailable) {
                ConsoleOutput::print("&l1&r&7. [&2✓&7] MySQL");
            } else {
                ConsoleOutput::print("&l1&r&7. [&4×&7] MySQL &4(Missing PDO driver)");
            }

            // SQLite option is always available
            ConsoleOutput::print("&l2&r&7. [&2✓&7] SQLite\n");

            $dbChoice = trim((string)fgets(STDIN));
            ConsoleOutput::print("\nYou've selected: " . $dbChoice);
            ConsoleOutput::hr();

            // Handle the user's choice
            switch ($dbChoice) {
                case '0':
                case '':
                    ConsoleOutput::print("Exiting the database creation process.");
                    return;  // Exit the loop and terminate the script
                case '1':
                    if ($mysqlAvailable) {
                        $mysqlMaker = new MakeMySQL();
                        $mysqlMaker->execute();
                        return;  // Exit the loop after successful MySQL execution
                    } else {
                        ConsoleOutput::print("&l&4[Error]&7 MySQL is not available on this system.");
                    }
                    break;
                case '2':
                    $sqliteMaker = new MakeSQLite();
                    $sqliteMaker->execute();
                    return;  // Exit the loop after successful SQLite execution
                default:
                    ConsoleOutput::print("&4[Error]&7 Invalid choice. Please enter &l0&r&7 to exit, &l1&r&7 for MySQL, or &l2&r&7 for SQLite.");
                    break;
            }
        } while (true);  // Continue the loop until a valid choice is made or the user exits
    }
}
