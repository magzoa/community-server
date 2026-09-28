<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndAdminSeeder extends Seeder
{
    /**
     * Crea los roles base y un usuario admin inicial.
     * Credenciales placeholder: cambiar en producción.
     */
    public function run(): void
    {
        // Roles del sistema. Se usa el guard por defecto "web": Sanctum
        // resuelve el token contra el provider del guard por defecto, y
        // Spatie evalúa los roles del modelo User bajo ese mismo guard.
        foreach (['admin', 'member', 'member_active'] as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        // Usuario admin inicial (idempotente)
        $admin = User::firstOrCreate(
            ['email' => 'admin@community.test'],
            [
                'name' => 'Administrador',
                'nickname' => 'admin',
                'password' => Hash::make('password'),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Perfil de miembro del admin (aprobado)
        Member::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'first_name' => 'Administrador',
                'status' => Member::STATUS_APPROVED,
                'approved_at' => now(),
            ]
        );
    }
}
