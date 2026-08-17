<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'portal:create-admin';

    protected $description = 'Create a portal administrator';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Name'), 'email' => $this->ask('Email'), 'password' => $this->secret('Password')];
        $v = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email:rfc,dns|max:255|unique:users,email', 'password' => ['required', 'string', 'min:12', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^a-zA-Z0-9]/']]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }User::create($data + ['is_admin' => true, 'is_active' => true, 'email_verified_at' => now()]);
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
