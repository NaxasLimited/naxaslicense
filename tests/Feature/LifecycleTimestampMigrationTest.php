<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LifecycleTimestampMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_or_mariadb_uses_strict_mode_when_selected(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Run with the mysql connection to verify the server SQL mode.');
        }

        $sqlMode = (string) DB::scalar('select @@SESSION.sql_mode');

        $this->assertStringContainsString('STRICT_', $sqlMode);
        $this->assertMatchesRegularExpression('/NO_ZERO_(DATE|IN_DATE)/', $sqlMode);
    }

    #[DataProvider('nullableLifecycleTimestamps')]
    public function test_lifecycle_timestamps_are_nullable_without_zero_date_defaults(
        string $table,
        string $column,
    ): void {
        $definition = collect(Schema::getColumns($table))->firstWhere('name', $column);

        $this->assertNotNull($definition, "Missing {$table}.{$column}");
        $this->assertTrue($definition['nullable'], "{$table}.{$column} must be nullable");
        $this->assertNotSame('0000-00-00 00:00:00', $definition['default']);
    }

    public static function nullableLifecycleTimestamps(): array
    {
        return [
            'license support expiry' => ['licenses', 'support_expires_at'],
            'license issuance' => ['licenses', 'issued_at'],
            'license expiry' => ['licenses', 'expires_at'],
            'license suspension' => ['licenses', 'suspended_at'],
            'license revocation' => ['licenses', 'revoked_at'],
            'request approval' => ['activation_requests', 'approved_at'],
            'request rejection' => ['activation_requests', 'rejected_at'],
            'request completion' => ['activation_requests', 'completed_at'],
            'activation deactivation' => ['license_activations', 'deactivated_at'],
            'user last login' => ['users', 'last_login_at'],
        ];
    }
}
