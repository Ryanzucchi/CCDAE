<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Distrito;
use Illuminate\Support\Facades\DB;

class DistritoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('TRUNCATE TABLE distritos CASCADE;');

        $distritos = [
        ];

        Distrito::insert($distritos);
    }
}
