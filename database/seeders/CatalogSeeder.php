<?php

namespace Database\Seeders;

use App\Models\CommunityRole;
use App\Models\ProfessionalProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Siembra los catálogos de roles de comunidad y perfiles profesionales
     * con colores por defecto (editables luego desde el ABM).
     */
    public function run(): void
    {
        // Roles de comunidad: [name, color]
        $communityRoles = [
            ['Líder', '#D32F2F'],
            ['Staff', '#1976D2'],
            ['Speaker', '#7B1FA2'],
            ['Mentor', '#00897B'],
            ['Auspiciante', '#F57C00'],
            ['Colaborador', '#5D4037'],
            ['Miembro', '#455A64'],
        ];

        foreach ($communityRoles as $i => [$name, $color]) {
            CommunityRole::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'color' => $color, 'is_active' => true, 'sort_order' => $i]
            );
        }

        // Perfiles profesionales: [name, color]
        $profiles = [
            ['CEO / Founder', '#C2185B'],
            ['CTO', '#512DA8'],
            ['Cloud Architect', '#0288D1'],
            ['Desarrollador', '#388E3C'],
            ['DevOps / SRE', '#00796B'],
            ['Data Engineer', '#F9A825'],
            ['QA / Tester', '#E64A19'],
            ['Product Manager', '#303F9F'],
            ['Diseñador UX/UI', '#AD1457'],
            ['Estudiante', '#616161'],
            ['Otro', '#9E9E9E'],
        ];

        foreach ($profiles as $i => [$name, $color]) {
            ProfessionalProfile::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'color' => $color, 'is_active' => true, 'sort_order' => $i]
            );
        }
    }
}
