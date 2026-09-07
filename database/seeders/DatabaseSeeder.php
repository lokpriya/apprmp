<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // InstitutionTypesSeeder is deliberately not called: institution types now
        // live in survey_options (seeded by SurveyOptionSeeder) since the
        // 2026_08_27 migration. The institution_types table is retained only so
        // that migration's down() can still roll back, and nothing reads it.
        $this->call([
            NepalMasterDataSeeder::class,
            MasterDataSeeder::class,
            DisasterOptionSeeder::class,
            TestUsersSeeder::class,
        ]);
    }
}
