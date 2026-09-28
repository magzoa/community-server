<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class AdminMemberController extends Controller
{
    /**
     * Lista miembros, filtrable por estado (?status=pending) y paginado.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100)); // límite de seguridad

        $query = Member::with(
            'socialLinks',
            'user:id,name,email',
            'user.roles:id,name',
            'professionalProfile',
            'communityRoles'
        );

        $status = $request->query('status');
        if ($status && in_array($status, [
            Member::STATUS_PENDING,
            Member::STATUS_APPROVED,
            Member::STATUS_REJECTED,
        ], true)) {
            $query->where('status', $status);
        }

        // Filtro por perfil profesional (id)
        if ($profileId = $request->query('professional_profile')) {
            $query->where('professional_profile_id', $profileId);
        }

        // Filtro por rol de comunidad (id)
        if ($roleId = $request->query('community_role')) {
            $query->whereHas('communityRoles', fn ($q) => $q->where('community_roles.id', $roleId));
        }

        $members = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => __('members.list_ok'),
            'data' => $members->items(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    /**
     * Detalle de un miembro con sus redes y usuario.
     */
    public function show(int $id): JsonResponse
    {
        $member = Member::with(
            'socialLinks',
            'user:id,name,email',
            'user.roles:id,name',
            'professionalProfile',
            'communityRoles'
        )->find($id);

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        return response()->json([
            'status' => true,
            'member' => $member,
        ]);
    }

    /**
     * Aprueba a un miembro: cambia estado, registra auditoría y
     * suma el rol "member_active" (acumulativo, conserva "member").
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $member = Member::with('user')->find($id);

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        if ($member->status === Member::STATUS_APPROVED) {
            return response()->json([
                'status' => false,
                'message' => __('members.already_approved'),
            ], 409);
        }

        DB::transaction(function () use ($member, $request) {
            $member->update([
                'status' => Member::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            // Suma el rol de miembro activo sin quitar "member"
            $member->user->assignRole('member_active');
        });

        $member->load('socialLinks', 'user:id,name,email', 'user.roles:id,name');

        return response()->json([
            'status' => true,
            'message' => __('members.approved'),
            'member' => $member,
        ]);
    }

    /**
     * Rechaza a un miembro: cambia estado y registra auditoría.
     * Mantiene el rol "member" (puede volver a solicitar).
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $member = Member::find($id);

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        $member->update([
            'status' => Member::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => __('members.rejected'),
            'member' => $member,
        ]);
    }

    /**
     * Lista los roles disponibles del sistema (para poblar la UI).
     */
    public function availableRoles(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Sincroniza los roles de un miembro (enfoque syncRoles): la lista
     * enviada reemplaza por completo los roles actuales del usuario.
     * El acceso a las funciones del sistema se decide por roles, no por estado.
     */
    public function updateRoles(Request $request, int $id): JsonResponse
    {
        $member = Member::with('user')->find($id);

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        // Solo se aceptan nombres de roles existentes
        $existingRoles = Role::pluck('name')->all();

        $validator = Validator::make($request->all(), [
            'roles' => 'present|array',
            'roles.*' => ['string', 'in:'.implode(',', $existingRoles)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $newRoles = $request->input('roles', []);
        $targetUser = $member->user;

        // Protección: no dejar el sistema sin ningún admin.
        // Si este usuario es admin y se le quita el rol admin, verificar
        // que quede al menos otro admin.
        $removingAdmin = $targetUser->hasRole('admin') && ! in_array('admin', $newRoles, true);
        if ($removingAdmin) {
            // Los roles usan el guard 'web' (ver RoleAndAdminSeeder)
            $otherAdmins = Role::findByName('admin', 'web')->users()
                ->where('users.id', '!=', $targetUser->id)
                ->count();

            if ($otherAdmins === 0) {
                return response()->json([
                    'status' => false,
                    'message' => __('members.last_admin'),
                ], 422);
            }
        }

        // syncRoles reemplaza el set completo de roles
        $targetUser->syncRoles($newRoles);

        $member->load('socialLinks', 'user:id,name,email', 'user.roles:id,name');

        return response()->json([
            'status' => true,
            'message' => __('members.roles_updated'),
            'member' => $member,
        ]);
    }

    /**
     * Elimina definitivamente a un miembro y su usuario (borrado en cascada:
     * member + social_links por FK; tokens + roles por el hook del modelo User).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $member = Member::with('user')->find($id);

        if (! $member || ! $member->user) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        $targetUser = $member->user;

        // Protección: un admin no puede eliminarse a sí mismo
        if ($targetUser->id === $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => __('members.cannot_delete_self'),
            ], 422);
        }

        // Protección: no dejar el sistema sin ningún admin
        if ($targetUser->hasRole('admin')) {
            $otherAdmins = Role::findByName('admin', 'web')->users()
                ->where('users.id', '!=', $targetUser->id)
                ->count();

            if ($otherAdmins === 0) {
                return response()->json([
                    'status' => false,
                    'message' => __('members.last_admin_delete'),
                ], 422);
            }
        }

        // Borrado atómico: al eliminar el user se disparan las cascadas
        DB::transaction(function () use ($targetUser) {
            $targetUser->delete();
        });

        return response()->json([
            'status' => true,
            'message' => __('members.deleted'),
        ]);
    }

    /**
     * Edita los catálogos de un miembro (perfil profesional + roles de
     * comunidad). Solo admin; opera sobre cualquier miembro.
     */
    public function updateCatalogs(Request $request, int $id): JsonResponse
    {
        $member = Member::find($id);

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'professional_profile_id' => 'nullable|exists:professional_profiles,id',
            'community_roles' => 'nullable|array',
            'community_roles.*' => 'integer|exists:community_roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $member->update([
            'professional_profile_id' => $request->input('professional_profile_id'),
        ]);
        $member->communityRoles()->sync($request->input('community_roles', []));

        $member->load(
            'socialLinks',
            'user:id,name,email',
            'user.roles:id,name',
            'professionalProfile',
            'communityRoles'
        );

        return response()->json([
            'status' => true,
            'message' => __('members.catalogs_updated'),
            'member' => $member,
        ]);
    }
}
