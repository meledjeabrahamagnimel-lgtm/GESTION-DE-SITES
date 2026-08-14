<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            EntrepriseArtisanAutomobileSeeder::class,
            DonneesOperationnellesSeeder::class,
            VillesBouakeSanPedroSeeder::class,
            MessagerieEtNotesSeeder::class,
        ]);
    }
}
