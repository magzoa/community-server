<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
            'user:id,name,nickname,email',
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

        // Búsqueda por nombre y apellido
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
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

    /**
     * Edición completa de un miembro por el admin: datos del Member + name/email
     * de la cuenta + catálogos. NO edita nickname ni contraseña.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $member = Member::with('user')->find($id);

        if (! $member || ! $member->user) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        $userId = $member->user->id;

        $validator = Validator::make($request->all(), [
            // Cuenta (sin nickname ni password)
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Perfil del member
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            // Catálogos
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

        DB::transaction(function () use ($request, $member) {
            // Datos de la cuenta (name, email)
            $member->user->update([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
            ]);

            // Datos del perfil
            $member->update([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'phone' => $request->input('phone'),
                'contact_email' => $request->input('contact_email'),
                'country' => $request->input('country'),
                'city' => $request->input('city'),
                'company' => $request->input('company'),
                'job_title' => $request->input('job_title'),
                'bio' => $request->input('bio'),
                'professional_profile_id' => $request->input('professional_profile_id'),
            ]);

            $member->communityRoles()->sync($request->input('community_roles', []));
        });

        $member->load(
            'socialLinks',
            'user:id,name,nickname,email',
            'user.roles:id,name',
            'professionalProfile',
            'communityRoles'
        );

        return response()->json([
            'status' => true,
            'message' => __('members.updated'),
            'member' => $member,
        ]);
    }

    /**
     * Cambio de nickname (proceso sensible, aparte del update general).
     * Mueve la carpeta de archivos users/<viejo> → users/<nuevo>
     * (copiar → verificar → borrar) y regenera avatar_url. Si la copia
     * falla, aborta sin tocar la BD ni la carpeta original.
     */
    public function updateNickname(Request $request, int $id, MemberFileService $files): JsonResponse
    {
        $member = Member::with('user')->find($id);

        if (! $member || ! $member->user) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        $userId = $member->user->id;

        $validator = Validator::make($request->all(), [
            'nickname' => [
                'required', 'string', 'max:30', 'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'nickname')->ignore($userId),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $oldNickname = $member->user->nickname;
        $newNickname = $request->input('nickname');

        // Sin cambios: nada que hacer
        if ($oldNickname === $newNickname) {
            return response()->json([
                'status' => true,
                'message' => __('members.nickname_updated'),
                'member' => $member,
            ]);
        }

        try {
            // 1) Mover archivos (copiar/verificar/borrar). Si falla, lanza excepción.
            $files->renameUserFolder($oldNickname, $newNickname);

            // 2) Actualizar nickname y regenerar avatar_url en transacción
            DB::transaction(function () use ($member, $newNickname, $files) {
                $member->user->update(['nickname' => $newNickname]);

                // Regenera la URL del avatar apuntando a la nueva carpeta
                $newAvatar = $member->avatar_url
                    ? $files->currentAvatarUrl($newNickname)
                    : null;
                $member->update(['avatar_url' => $newAvatar]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => __('members.nickname_move_failed'),
            ], 500);
        }

        $member->load(
            'socialLinks',
            'user:id,name,nickname,email',
            'user.roles:id,name',
            'professionalProfile',
            'communityRoles'
        );

        return response()->json([
            'status' => true,
            'message' => __('members.nickname_updated'),
            'member' => $member,
        ]);
    }

    /**
     * Alta de un miembro por parte del admin: crea User + Member (aprobado)
     * con roles member+member_active, perfil profesional y roles de comunidad.
     * La contraseña la define el admin (viene preseteada desde el cliente).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Cuenta
            'name' => 'required|string|max:255',
            'nickname' => 'required|string|max:30|regex:/^[a-zA-Z0-9._-]+$/|unique:users,nickname',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:5',
            // Perfil (datos públicos)
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            // Catálogos
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

        $member = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('name'),
                'nickname' => $request->input('nickname'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);

            // Creado por admin: se avala directamente (member + member_active)
            $user->assignRole(['member', 'member_active']);

            $member = Member::create([
                'user_id' => $user->id,
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'phone' => $request->input('phone'),
                'contact_email' => $request->input('contact_email') ?: $user->email,
                'country' => $request->input('country'),
                'city' => $request->input('city'),
                'company' => $request->input('company'),
                'job_title' => $request->input('job_title'),
                'bio' => $request->input('bio'),
                'professional_profile_id' => $request->input('professional_profile_id'),
                'status' => Member::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $member->communityRoles()->sync($request->input('community_roles', []));

            return $member;
        });

        $member->load(
            'socialLinks',
            'user:id,name,nickname,email',
            'user.roles:id,name',
            'professionalProfile',
            'communityRoles'
        );

        return response()->json([
            'status' => true,
            'message' => __('members.created'),
            'member' => $member,
        ], 201);
    }

    /**
     * Sube/reemplaza el avatar de un miembro dado (admin).
     * Guarda en users/<nickname>/perfil, borrando la carpeta antes.
     */
    public function uploadAvatarFor(Request $request, int $id): JsonResponse
    {
        $member = Member::with('user')->find($id);

        if (! $member || ! $member->user) {
            return response()->json([
                'status' => false,
                'message' => __('members.member_not_found'),
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $disk = env('MEMBER_FILES_DISK', 'public');
        $folder = "users/{$member->user->nickname}/perfil";

        Storage::disk($disk)->deleteDirectory($folder);

        $ext = $request->file('avatar')->getClientOriginalExtension() ?: 'jpg';
        $path = $request->file('avatar')->storeAs($folder, "avatar.{$ext}", $disk);

        $member->update(['avatar_url' => Storage::disk($disk)->url($path).'?v='.time()]);

        return response()->json([
            'status' => true,
            'message' => __('members.avatar_updated'),
            'member' => $member,
        ]);
    }
}
