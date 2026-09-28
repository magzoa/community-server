<?php

namespace App\Http\Controllers;

use App\Models\MemberSocialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /**
     * Disco para archivos de miembros (público). Para migrar a S3 basta
     * definir MEMBER_FILES_DISK=s3 en .env (el disco s3 ya está configurado).
     */
    private function disk(): string
    {
        return env('MEMBER_FILES_DISK', 'public');
    }

    /**
     * Carpeta de perfil del miembro autenticado: users/<nickname>/perfil
     */
    private function profileFolder(Request $request): string
    {
        $nickname = $request->user()->nickname;

        return "users/{$nickname}/perfil";
    }
    /**
     * Devuelve el perfil del miembro autenticado con sus redes.
     */
    public function showProfile(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.profile_not_found'),
            ], 404);
        }

        $member->load('socialLinks', 'user:id,name,nickname,email', 'professionalProfile', 'communityRoles');

        return response()->json([
            'status' => true,
            'member' => $member,
        ]);
    }

    /**
     * Actualiza los datos del perfil propio.
     * No permite modificar estado ni datos de aprobación.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.profile_not_found'),
            ], 404);
        }

        $hexColor = 'regex:/^#([0-9A-Fa-f]{6})$/';

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'avatar_url' => 'nullable|url|max:255',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'professional_profile_id' => 'nullable|exists:professional_profiles,id',
            'community_roles' => 'nullable|array',
            'community_roles.*' => 'integer|exists:community_roles,id',
            'primary_color' => "nullable|string|{$hexColor}",
            'secondary_color' => "nullable|string|{$hexColor}",
            'text_color' => "nullable|string|{$hexColor}",
            'background_color' => "nullable|string|{$hexColor}",
            'theme' => 'nullable|string|in:light,dark,auto',
            'banner_url' => 'nullable|url|max:255',
        ]);

        // Los roles de comunidad se sincronizan aparte (pivote)
        $communityRoles = $validated['community_roles'] ?? null;
        unset($validated['community_roles']);

        $member->update($validated);

        if ($communityRoles !== null) {
            $member->communityRoles()->sync($communityRoles);
        }

        $member->load('socialLinks', 'user:id,name,nickname,email', 'professionalProfile', 'communityRoles');

        return response()->json([
            'status' => true,
            'message' => __('members.profile_updated'),
            'member' => $member,
        ]);
    }

    /**
     * Agrega una red social al miembro autenticado.
     */
    public function addSocialLink(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.profile_not_found'),
            ], 404);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(MemberSocialLink::TYPES)],
            'url' => 'required|url|max:255',
            'label' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'image_url' => 'nullable|url|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $link = $member->socialLinks()->create($validated);

        return response()->json([
            'status' => true,
            'message' => __('members.link_added'),
            'social_link' => $link,
        ], 201);
    }

    /**
     * Actualiza una red social propia (verifica propiedad).
     */
    public function updateSocialLink(Request $request, int $id): JsonResponse
    {
        $member = $request->user()->member;

        $link = $member
            ? $member->socialLinks()->find($id)
            : null;

        if (! $link) {
            return response()->json([
                'status' => false,
                'message' => __('members.link_not_found'),
            ], 404);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(MemberSocialLink::TYPES)],
            'url' => 'required|url|max:255',
            'label' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'image_url' => 'nullable|url|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $link->update($validated);

        return response()->json([
            'status' => true,
            'message' => __('members.link_updated'),
            'social_link' => $link,
        ]);
    }

    /**
     * Elimina una red social propia (verifica propiedad).
     */
    public function deleteSocialLink(Request $request, int $id): JsonResponse
    {
        $member = $request->user()->member;

        $link = $member
            ? $member->socialLinks()->find($id)
            : null;

        if (! $link) {
            return response()->json([
                'status' => false,
                'message' => __('members.link_not_found'),
            ], 404);
        }

        $link->delete();

        return response()->json([
            'status' => true,
            'message' => __('members.link_deleted'),
        ]);
    }

    /**
     * Sube/reemplaza la foto de perfil del miembro autenticado.
     * Guarda en users/<nickname>/perfil, borrando antes todo el contenido
     * de esa carpeta para no acumular archivos.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.profile_not_found'),
            ], 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048', // 2 MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $disk = $this->disk();
        $folder = $this->profileFolder($request);

        // Limpia toda la carpeta de perfil antes de subir la nueva foto
        Storage::disk($disk)->deleteDirectory($folder);

        // Guarda como avatar.<ext>
        $ext = $request->file('avatar')->getClientOriginalExtension() ?: 'jpg';
        $path = $request->file('avatar')->storeAs($folder, "avatar.{$ext}", $disk);

        // URL pública con parámetro de versión (evita caché del navegador)
        $url = Storage::disk($disk)->url($path).'?v='.time();

        $member->update(['avatar_url' => $url]);
        $member->load('socialLinks', 'user:id,name,nickname,email', 'professionalProfile', 'communityRoles');

        return response()->json([
            'status' => true,
            'message' => __('members.avatar_updated'),
            'member' => $member,
        ]);
    }

    /**
     * Elimina la foto de perfil del miembro autenticado.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json([
                'status' => false,
                'message' => __('members.profile_not_found'),
            ], 404);
        }

        // Borra toda la carpeta de perfil y limpia el campo
        Storage::disk($this->disk())->deleteDirectory($this->profileFolder($request));
        $member->update(['avatar_url' => null]);
        $member->load('socialLinks', 'user:id,name,nickname,email', 'professionalProfile', 'communityRoles');

        return response()->json([
            'status' => true,
            'message' => __('members.avatar_deleted'),
            'member' => $member,
        ]);
    }
}
