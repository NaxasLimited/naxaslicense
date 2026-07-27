<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_public_registration_is_disabled(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertNotFound();

        $this->assertGuest();
    }

    public function test_administrator_accounts_are_provisioned_by_the_console_command(): void
    {
        $this->assertArrayHasKey('portal:create-admin', Artisan::all());
    }
}
