<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateAdmin extends Command
{
    protected $signature = 'portal:create-admin';

    protected $description = 'Create a portal administrator';

    public function handle(): int
    {
        $email = $this->ask('Email');
        $emailValidator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email:rfc', 'max:255']],
        );

        if ($emailValidator->fails()) {
            return $this->reportValidationErrors($emailValidator);
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null) {
            if (! $this->confirm('A user with this email already exists. Promote and activate this user?', false)) {
                $this->warn('Existing user was not changed.');

                return self::FAILURE;
            }

            $existingUser->update([
                'is_admin' => true,
                'is_active' => true,
            ]);

            $this->info('Existing user promoted to administrator.');

            return self::SUCCESS;
        }

        $data = [
            'name' => $this->ask('Name'),
            'email' => $email,
            'password' => $this->secret('Password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => [
                'required',
                'string',
                'min:12',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^a-zA-Z0-9]/',
            ],
        ]);

        if ($validator->fails()) {
            return $this->reportValidationErrors($validator);
        }

        User::create($validator->validated() + [
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->info('Administrator created.');

        return self::SUCCESS;
    }

    private function reportValidationErrors(\Illuminate\Contracts\Validation\Validator $validator): int
    {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return self::FAILURE;
    }
}
