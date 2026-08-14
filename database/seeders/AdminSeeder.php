<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrNew([
            'email' => 'admin@armooutdoor.test',
        ]);

        $admin->first_name = 'Admin';
        $admin->last_name = 'Armo Outdoor';
        $admin->password = 'password';
        $admin->is_admin = true;
        $admin->save();
    }
}
