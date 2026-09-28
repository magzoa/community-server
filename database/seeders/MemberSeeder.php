<?php

namespace Database\Seeders;

use App\Models\CommunityRole;
use App\Models\Member;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MemberSeeder extends Seeder
{
    /**
     * Crea usuarios + miembros de ejemplo (aprobados, sin foto) para poblar
     * el home público, con perfil profesional y roles de comunidad asignados.
     */
    public function run(): void
    {
        // [nombre, apellido, nickname, slug perfil profesional, [slugs roles comunidad]]
        $data = [
            ['María', 'González', 'maria.g', 'cloud-architect', ['lider', 'speaker']],
            ['Carlos', 'Ramírez', 'carlos.r', 'devops-sre', ['staff']],
            ['Lucía', 'Fernández', 'lucia.f', 'desarrollador', ['miembro']],
            ['Javier', 'Torres', 'javier.t', 'data-engineer', ['mentor']],
            ['Ana', 'Duarte', 'ana.d', 'qa-tester', ['colaborador']],
            ['Diego', 'Benítez', 'diego.b', 'cto', ['lider', 'staff']],
            ['Sofía', 'Rojas', 'sofia.r', 'disenador-uxui', ['speaker']],
            ['Martín', 'Acosta', 'martin.a', 'product-manager', ['staff']],
            ['Valeria', 'Núñez', 'valeria.n', 'ceo-founder', ['auspiciante']],
            ['Pedro', 'Ortiz', 'pedro.o', 'estudiante', ['miembro']],
        ];

        $profiles = ProfessionalProfile::pluck('id', 'slug');
        $roles = CommunityRole::pluck('id', 'slug');

        foreach ($data as $i => [$first, $last, $nickname, $profileSlug, $roleSlugs]) {
            $user = User::firstOrCreate(
                ['email' => "{$nickname}@community.test"],
                [
                    'name' => $first,
                    'nickname' => $nickname,
                    'password' => Hash::make('password'),
                ]
            );

            // Miembros de ejemplo: aprobados y con rol member_active
            $user->syncRoles(['member', 'member_active']);

            $member = Member::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => '09'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                    'contact_email' => "{$nickname}@community.test",
                    'country' => 'Paraguay',
                    'city' => 'Asunción',
                    'company' => 'Comunidad AWS',
                    'status' => Member::STATUS_APPROVED,
                    'approved_at' => now(),
                    'professional_profile_id' => $profiles[$profileSlug] ?? null,
                ]
            );

            // Roles de comunidad (N:N)
            $ids = collect($roleSlugs)->map(fn ($s) => $roles[$s] ?? null)->filter()->all();
            $member->communityRoles()->sync($ids);
        }
    }
}
