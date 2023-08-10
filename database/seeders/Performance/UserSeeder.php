<?php

namespace Database\Seeders\Performance;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(int $owners = 100, int $users = 100): void
    {
        $newUsers = User::factory($owners)->create();
        $ownerRole = Role::findById(Role::ROLE_OWNER);
        $ownerRole->users()->attach($newUsers->pluck('id')->toArray());

        $newUsers = User::factory($users)->create();
        $userRole = Role::findById(Role::ROLE_USER);
        $userRole->users()->attach($newUsers->pluck('id')->toArray());
    }
}
