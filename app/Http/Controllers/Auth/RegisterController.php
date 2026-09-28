<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberSocialLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    /**
     * Registro de un nuevo miembro de la comunidad.
     * Crea User + Member(pending) + redes, asigna rol "member"
     * y devuelve un token de inmediato.
     */
    public function register(Request $request): JsonResponse
    {
        // Regla hex para los colores de personalización (#RRGGBB)
        $hexColor = 'regex:/^#([0-9A-Fa-f]{6})$/';

        $validator = Validator::make($request->all(), [
            // Credenciales
            'name' => 'required|string|max:255',
            'nickname' => 'required|string|max:30|regex:/^[a-zA-Z0-9._-]+$/|unique:users,nickname',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:5',

            // Perfil de miembro
            'member.first_name' => 'required|string|max:255',
            'member.last_name' => 'nullable|string|max:255',
            'member.phone' => 'nullable|string|max:50',
            'member.contact_email' => 'nullable|email|max:255',
            'member.country' => 'nullable|string|max:255',
            'member.city' => 'nullable|string|max:255',
            'member.bio' => 'nullable|string',
            'member.avatar_url' => 'nullable|url|max:255',
            'member.company' => 'nullable|string|max:255',
            'member.job_title' => 'nullable|string|max:255',

            // Personalización
            'member.primary_color' => "nullable|string|{$hexColor}",
            'member.secondary_color' => "nullable|string|{$hexColor}",
            'member.text_color' => "nullable|string|{$hexColor}",
            'member.background_color' => "nullable|string|{$hexColor}",
            'member.theme' => 'nullable|string|in:light,dark,auto',
            'member.banner_url' => 'nullable|url|max:255',

            // Redes sociales (se permiten duplicados de tipo)
            'social_links' => 'nullable|array',
            'social_links.*.type' => ['required_with:social_links', Rule::in(MemberSocialLink::TYPES)],
            'social_links.*.url' => 'required_with:social_links|url|max:255',
            'social_links.*.label' => 'nullable|string|max:255',
            'social_links.*.icon' => 'nullable|string|max:255',
            'social_links.*.image_url' => 'nullable|url|max:255',
            'social_links.*.sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        // Todo o nada: user + member + redes
        $result = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('name'),
                'nickname' => $request->input('nickname'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);

            // Rol inicial (nunca se quita); member_active se suma al aprobar
            $user->assignRole('member');

            $memberData = $request->input('member', []);
            $memberData['user_id'] = $user->id;
            $memberData['status'] = Member::STATUS_PENDING;
            // Por defecto, el correo de contacto es el de la cuenta
            if (empty($memberData['contact_email'])) {
                $memberData['contact_email'] = $user->email;
            }

            $member = Member::create($memberData);

            // Redes sociales opcionales
            foreach ($request->input('social_links', []) as $link) {
                $member->socialLinks()->create($link);
            }

            return $user;
        });

        // Token Bearer inmediato tras el registro
        $token = $result->createToken('api')->plainTextToken;
        $result->load('member.socialLinks', 'roles:id,name');

        return response()->json([
            'status' => true,
            'message' => __('auth.registered'),
            'token' => $token,
            'user' => $result,
            'roles' => $result->roles->pluck('name'),
        ], 201);
    }

    /**
     * Verifica disponibilidad de un nickname (para aviso en vivo en el cliente).
     * Público: no revela datos sensibles, solo si está tomado.
     */
    public function checkNickname(Request $request): JsonResponse
    {
        $nickname = (string) $request->query('nickname', '');

        // Formato válido mínimo
        $validFormat = $nickname !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $nickname) === 1;

        $available = $validFormat
            && ! User::where('nickname', $nickname)->exists();

        return response()->json([
            'status' => true,
            'available' => $available,
            'valid_format' => (bool) $validFormat,
        ]);
    }
}
