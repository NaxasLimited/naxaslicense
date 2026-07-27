<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_administrator_can_be_created_without_exposing_the_password(): void
    {
        $password = 'SecurePassword123!';

        $this->artisan('portal:create-admin')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsQuestion('Name', 'Portal Administrator')
            ->expectsQuestion('Password', $password)
            ->expectsOutput('Administrator created.')
            ->doesntExpectOutput($password)
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->sole();

        $this->assertSame('Portal Administrator', $user->name);
        $this->assertSame('admin@example.com', $user->email);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_active);
    }

    public function test_an_existing_user_is_not_silently_overwritten(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'OriginalPassword123!',
            'is_admin' => false,
            'is_active' => false,
        ]);
        $originalPasswordHash = $user->password;

        $this->artisan('portal:create-admin')
            ->expectsQuestion('Email', 'existing@example.com')
            ->expectsConfirmation(
                'A user with this email already exists. Promote and activate this user?',
                'no',
            )
            ->expectsOutput('Existing user was not changed.')
            ->assertFailed();

        $user->refresh();

        $this->assertSame('Existing User', $user->name);
        $this->assertSame($originalPasswordHash, $user->password);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->is_active);
    }

    public function test_an_existing_user_requires_confirmation_before_promotion(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'is_admin' => false,
            'is_active' => false,
        ]);
        $originalAttributes = $user->only(['name', 'email', 'password']);

        $this->artisan('portal:create-admin')
            ->expectsQuestion('Email', 'existing@example.com')
            ->expectsConfirmation(
                'A user with this email already exists. Promote and activate this user?',
                'yes',
            )
            ->expectsOutput('Existing user promoted to administrator.')
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame($originalAttributes, $user->only(['name', 'email', 'password']));
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_only_approved_attributes_are_mass_assignable(): void
    {
        $user = new User;

        $this->assertSame([
            'name',
            'email',
            'password',
            'is_admin',
            'is_active',
            'last_login_at',
        ], $user->getFillable());

        $user->fill([
            'name' => 'Allowed Name',
            'email_verified_at' => now(),
            'remember_token' => 'not-allowed',
        ]);

        $this->assertSame('Allowed Name', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->remember_token);
        $this->assertContains('password', $user->getHidden());
        $this->assertContains('remember_token', $user->getHidden());
    }
}
