<?php

namespace Tests\Unit;

use App\Events\InventoryUpdated;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Broadcast;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MigrationPreservationConfigurationPropertyTest extends TestCase
{
    /**
     * Property 2: Preservation - Existing Data, Behavior, and Test Isolation.
     *
     * **Validates: Requirements 3.3, 3.6**
     *
     * @param array{APP_ENV: string, DB_CONNECTION: string, DB_DATABASE: string, DB_URL: string} $environment
     */
    #[DataProvider('databaseContexts')]
    public function test_only_an_explicit_in_memory_sqlite_test_environment_is_safe(
        array $environment,
        bool $expectedSafe
    ): void {
        self::assertSame($expectedSafe, self::isSafeTestDatabase($environment));
    }

    /**
     * @return iterable<string, array{0: array{APP_ENV: string, DB_CONNECTION: string, DB_DATABASE: string, DB_URL: string}, 1: bool}>
     */
    public static function databaseContexts(): iterable
    {
        $safe = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        yield 'explicit sqlite memory database' => [$safe, true];
        yield 'non-testing environment' => [array_replace($safe, ['APP_ENV' => 'local']), false];
        yield 'postgresql connection' => [array_replace($safe, ['DB_CONNECTION' => 'pgsql']), false];
        yield 'file-backed sqlite database' => [array_replace($safe, ['DB_DATABASE' => 'database/test.sqlite']), false];
        yield 'database URL can override isolated fields' => [array_replace($safe, ['DB_URL' => 'sqlite:///database/test.sqlite']), false];
        yield 'postgresql URL is unsafe even with sqlite fields' => [array_replace($safe, ['DB_URL' => 'postgresql://placeholder.invalid/test']), false];
    }

    /**
     * Property 2: Preservation - Existing Data, Behavior, and Test Isolation.
     *
     * **Validates: Requirements 3.3, 3.6**
     */
    public function test_phpunit_resolves_to_the_isolated_in_memory_sqlite_database(): void
    {
        $resolved = [
            'APP_ENV' => (string) config('app.env'),
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_DATABASE' => (string) config('database.connections.sqlite.database'),
            'DB_URL' => (string) (config('database.connections.sqlite.url') ?? ''),
        ];

        self::assertTrue(self::isSafeTestDatabase($resolved));
        self::assertSame('sqlite', $this->app['db']->connection()->getDriverName());
        self::assertSame(['sqlite'], array_keys($this->app['db']->getConnections()));
    }

    /**
     * Property 2: Preservation - Existing Data, Behavior, and Test Isolation.
     *
     * **Validates: Requirements 3.4, 3.6**
     */
    public function test_generated_reverb_event_contracts_match_the_unfixed_baseline(): void
    {
        for ($sample = 0; $sample < 24; $sample++) {
            $pharmacyId = 101 + ($sample * 17);
            $medicineId = $sample % 3 === 0 ? null : 503 + ($sample * 19);
            $medicineName = $sample % 4 === 0 ? null : "Médicine {$sample} 薬";
            $stock = ($sample * 37) % 1000;
            $price = (($sample * 137) + 1) / 100;
            $requiresPrescription = $sample % 2 === 0;

            $inventory = new InventoryUpdated(
                $pharmacyId,
                $medicineId,
                $medicineName,
                $stock,
                $price,
                $requiresPrescription
            );

            self::assertSame(['inventory', "inventory.{$pharmacyId}"], self::channelNames($inventory->broadcastOn()));
            self::assertSame('inventory.updated', $inventory->broadcastAs());
            self::assertSame(
                [$pharmacyId, $medicineId, $medicineName, $stock, $price, $requiresPrescription],
                [
                    $inventory->pharmacyId,
                    $inventory->medicineId,
                    $inventory->medicineName,
                    $inventory->stock,
                    $inventory->price,
                    $inventory->prescription,
                ]
            );

            $direction = $sample % 2 === 0 ? 'consumer_to_pharmacy' : 'pharmacy_to_consumer';
            $reply = $sample % 2 === 0 ? null : "Reply {$sample}";
            $message = new MessageSent(
                701 + ($sample * 23),
                301 + ($sample * 29),
                $pharmacyId,
                "Message {$sample} — Unicode ✓",
                $sample % 5 === 0 ? null : "Consumer {$sample}",
                $direction,
                $reply
            );

            self::assertSame(["pharmacy.{$pharmacyId}"], self::channelNames($message->broadcastOn()));
            self::assertSame('message.sent', $message->broadcastAs());
            self::assertSame(
                [
                    701 + ($sample * 23),
                    301 + ($sample * 29),
                    $pharmacyId,
                    "Message {$sample} — Unicode ✓",
                    $sample % 5 === 0 ? null : "Consumer {$sample}",
                    $direction,
                    $reply,
                ],
                [
                    $message->messageId,
                    $message->consumerId,
                    $message->pharmacyId,
                    $message->message,
                    $message->consumerName,
                    $message->direction,
                    $message->reply,
                ]
            );
        }
    }

    /**
     * **Validates: Requirements 3.4**
     */
    public function test_channel_authorization_and_broadcast_selection_match_the_unfixed_baseline(): void
    {
        $channels = Broadcast::connection()->getChannels();
        $authorization = $channels->get('App.Models.User.{id}');

        self::assertNotNull($authorization);
        self::assertTrue($authorization((object) ['id' => 41], '41'));
        self::assertFalse($authorization((object) ['id' => 41], '42'));
        self::assertNull(config('broadcasting.default'));

        $templateDeclarations = self::activeEnvironmentValues('BROADCAST_CONNECTION');
        self::assertSame(['log', 'reverb'], $templateDeclarations);
    }

    /**
     * **Validates: Requirements 2.1, 2.6, 3.6**
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function postgreSqlTemplateSettings(): iterable
    {
        yield 'database engine' => ['DB_CONNECTION', 'pgsql'];
        yield 'host placeholder' => ['DB_HOST', '<postgres-host>'];
        yield 'standard port' => ['DB_PORT', '5432'];
        yield 'database placeholder' => ['DB_DATABASE', '<postgres-database>'];
        yield 'username placeholder' => ['DB_USERNAME', '<postgres-username>'];
        yield 'password placeholder' => ['DB_PASSWORD', '<replace-with-secret>'];
        yield 'UTF-8 charset' => ['DB_CHARSET', 'utf8'];
        yield 'TLS policy placeholder' => ['DB_SSLMODE', '<require-or-provider-required-mode>'];
    }

    #[DataProvider('postgreSqlTemplateSettings')]
    public function test_postgresql_template_declares_each_required_safe_setting(
        string $name,
        string $expectedValue
    ): void {
        self::assertSame([$expectedValue], self::activeEnvironmentValues($name));
    }

    /**
     * **Validates: Requirements 2.1, 2.6, 3.6**
     */
    public function test_database_url_is_documented_as_an_unset_precedence_based_alternative(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env.example');

        self::assertIsString($template);
        self::assertSame([], self::activeEnvironmentValues('DB_URL'));
        self::assertStringContainsString(
            'DB_URL is a complete PostgreSQL connection string and takes precedence over the individual DB_* settings below.',
            $template
        );
        self::assertStringContainsString('Leave it unset when using those settings; never configure conflicting values.', $template);
        self::assertStringContainsString('# DB_URL=<complete-postgresql-connection-url>', $template);
    }

    /**
     * **Validates: Requirements 3.3, 3.6**
     */
    public function test_database_configuration_keeps_environment_driven_overrides(): void
    {
        $configuration = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'database.php');

        self::assertIsString($configuration);
        foreach (['DB_URL', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET', 'DB_SSLMODE'] as $setting) {
            self::assertStringContainsString("env('{$setting}'", $configuration);
        }
    }

    /**
     * @param array{APP_ENV: string, DB_CONNECTION: string, DB_DATABASE: string, DB_URL: string} $environment
     */
    private static function isSafeTestDatabase(array $environment): bool
    {
        return $environment['APP_ENV'] === 'testing'
            && $environment['DB_CONNECTION'] === 'sqlite'
            && $environment['DB_DATABASE'] === ':memory:'
            && $environment['DB_URL'] === '';
    }

    /**
     * @param array<int, \Illuminate\Broadcasting\Channel> $channels
     * @return array<int, string>
     */
    private static function channelNames(array $channels): array
    {
        return array_map(static fn ($channel): string => $channel->name, $channels);
    }

    /**
     * @return array<int, string>
     */
    private static function activeEnvironmentValues(string $name): array
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env.example';
        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_starts_with($line, $name.'=')) {
                continue;
            }

            $values[] = trim(substr($line, strlen($name) + 1), " \t\n\r\0\x0B\"'");
        }

        return $values;
    }
}
