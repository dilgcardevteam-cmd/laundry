<?php

namespace Database\Seeders;

use App\Models\Service;
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
        // User::factory(10)->create();

        User::updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'administrator',
            'email_verified_at' => now(),
        ]);

        foreach ([
            ['name' => 'Wash, Dry and Fold', 'slug' => 'wash-dry-fold', 'pricing_method' => 'per_kg', 'rate' => 40, 'minimum_weight' => 5, 'estimated_processing_hours' => 48],
            ['name' => 'Dry Only', 'slug' => 'dry-only', 'pricing_method' => 'per_kg', 'rate' => 25, 'minimum_weight' => 3, 'estimated_processing_hours' => 24],
            ['name' => 'Comforter Cleaning', 'slug' => 'comforter-cleaning', 'pricing_method' => 'per_piece', 'rate' => 250, 'estimated_processing_hours' => 72],
        ] as $service) {
            Service::updateOrCreate(['slug' => $service['slug']], $service);
        }
    }
}
