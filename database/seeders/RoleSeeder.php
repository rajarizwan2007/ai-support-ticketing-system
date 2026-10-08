<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the fixed set of roles.
     */
    public function run(): void
    {
        foreach ([Role::ADMIN, Role::AGENT, Role::CUSTOMER] as $slug) {
            Role::findOrCreateBySlug($slug);
        }
    }
}
