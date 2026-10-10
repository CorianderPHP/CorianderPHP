<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Console\{CommandExitCode, Commands\Make\Route\MakeRoute};
use CorianderCore\Core\Router\RouteMap;
use CorianderCore\Core\Support\OutputBuffer;
use PHPUnit\Framework\Attributes\DataProvider;

class RouteValidationTest extends RouteFixtureTestCase
{
    #[DataProvider('invalidDefinitions')]
    public function testInvalidDefinitionIsRejectedBeforeWriting(string $file, string $message, bool $private): void
    {
        $this->put('index.get.php', 'throw new \\RuntimeException("Must not execute");');
        $map = new RouteMap();
        $before = $map->discover($this->directory);
        [$code, $output] = OutputBuffer::capture(fn() => (new MakeRoute($this->directory))->execute([$file]));
        self::assertSame(CommandExitCode::INVALID_USAGE, $code);
        self::assertStringContainsString($message, $output);
        self::assertFileDoesNotExist($this->directory . '/' . $file);
        if (dirname($file) !== '.') {
            self::assertDirectoryDoesNotExist($this->directory . '/' . dirname($file));
        }
        self::assertSame($before, $map->discover($this->directory));

        $this->put($file, 'throw new \\RuntimeException("Must not execute");');
        if ($private) {
            self::assertSame($before, $map->discover($this->directory));
        } else {
            $this->expectExceptionMessage($message);
            $map->discover($this->directory);
        }
    }

    /** @return array<string,array{string,string,bool}> */
    public static function invalidDefinitions(): array
    {
        return [
            'repeated parameter' => ['users/[id]/posts/[id].get.php', 'Repeated route parameter', false],
            'invalid parameter' => ['users/[9id].get.php', 'Invalid route segment', false],
            'invalid static segment' => ['users/bad name.get.php', 'Invalid route segment', false],
            'private directory' => ['_private/users.get.php', 'Invalid route file name', true],
            'private handler' => ['users/_hidden.get.php', 'Invalid route file name', true],
        ];
    }

    #[DataProvider('conflictingDefinitions')]
    public function testConflictUsesDiscoveryRulesAndPreservesExistingFiles(string $existing, string $candidate, string $message): void
    {
        $source = 'throw new \\RuntimeException("Must not execute");';
        $this->put($existing, $source);
        $this->put('_middleware.php', $source);
        $map = new RouteMap();
        $before = $map->discover($this->directory);
        $parent = dirname($this->directory . '/' . $candidate);
        $parentExisted = is_dir($parent);
        [$code, $output] = OutputBuffer::capture(fn() => (new MakeRoute($this->directory))->execute([$candidate]));
        self::assertSame(CommandExitCode::FAILURE, $code);
        self::assertStringContainsString($message, $output);
        self::assertFileDoesNotExist($this->directory . '/' . $candidate);
        self::assertSame('<?php ' . $source, file_get_contents($this->directory . '/' . $existing));
        self::assertSame($before, $map->discover($this->directory));
        if (!$parentExisted) {
            self::assertDirectoryDoesNotExist($parent);
        }

        $this->put($candidate, $source);
        $this->expectExceptionMessage($message);
        $map->discover($this->directory);
    }

    /** @return array<string,array{string,string,string}> */
    public static function conflictingDefinitions(): array
    {
        return [
            'index alias' => ['users.get.php', 'users/index.get.php', 'Duplicate GET route'],
            'reverse index alias' => ['users/index.get.php', 'users.get.php', 'Duplicate GET route'],
            'different method' => ['users/[id].get.php', 'users/[slug].post.php', 'Ambiguous parameter names'],
            'same method' => ['users/[id].get.php', 'users/[slug].get.php', 'Ambiguous parameter names'],
            'nested parameters' => ['teams/[team]/users/[id].get.php', 'teams/[teamId]/users/[id].patch.php', 'Ambiguous parameter names'],
        ];
    }

    public function testAllMethodsNestedParametersAndNonconflictingIndexAliasesAreAccepted(): void
    {
        $generator = new MakeRoute($this->directory);
        $methods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];
        foreach ($methods as $method) {
            [$code] = OutputBuffer::capture(fn() => $generator->execute(['teams/[team]/users/[id].' . $method . '.php']));
            self::assertSame(CommandExitCode::SUCCESS, $code);
        }
        foreach (['users.get.php', 'users/index.post.php', 'manual.v2.get.php'] as $file) {
            [$code] = OutputBuffer::capture(fn() => $generator->execute([$file]));
            self::assertSame(CommandExitCode::SUCCESS, $code);
        }
        $map = (new RouteMap())->discover($this->directory);
        self::assertSame(['team', 'id'], $map['dynamic'][0]['params']);
        self::assertEqualsCanonicalizing(array_map('strtoupper', $methods), array_keys($map['dynamic'][0]['methods']));
        self::assertCount(2, $map['static']['users']['methods']);
        self::assertArrayHasKey('manual.v2', $map['static']);
    }

    public function testInvalidExistingRoutesPreventNewFiles(): void
    {
        $this->put('users/[id]/posts/[id].get.php', 'throw new \\RuntimeException("Must not execute");');
        [$code, $output] = OutputBuffer::capture(fn() => (new MakeRoute($this->directory))->execute(['admin/index.get']));
        self::assertSame(CommandExitCode::FAILURE, $code);
        self::assertStringContainsString('Repeated route parameter', $output);
        self::assertDirectoryDoesNotExist($this->directory . '/admin');
    }
}
