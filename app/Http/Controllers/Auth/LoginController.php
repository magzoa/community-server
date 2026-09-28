<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * Login con email/contraseña. Devuelve token Bearer.
     * No bloquea por estado: la autorización la imponen los roles por ruta.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $user = User::where('email', $request->input('email'))->first();

        // Credenciales inválidas (usuario inexistente o contraseña incorrecta)
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'status' => false,
                'message' => __('auth.invalid_credentials'),
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;
        $user->load('member:id,user_id,status', 'roles:id,name');

        return response()->json([
            'status' => true,
            'message' => __('auth.login_success'),
            'token' => $token,
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
            'member_status' => $user->member?->status,
        ]);
    }

    /**
     * Cierra la sesión eliminando el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => __('auth.logged_out'),
        ]);
    }

    /**
     * Devuelve el usuario autenticado con su perfil, redes y roles.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('member.socialLinks', 'roles:id,name');

        return response()->json([
            'status' => true,
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
        ]);
    }
}
