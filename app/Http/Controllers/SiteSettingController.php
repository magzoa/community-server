<?php

namespace App\Http\Controllers;

use App\Models\BannerSocialLink;
use App\Models\Member;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SiteSettingController extends Controller
{
    /**
     * Configuración pública del banner del home (una sola petición):
     * textos + cantidad total de miembros + redes activas.
     */
    public function showPublic(): JsonResponse
    {
        $settings = SiteSetting::current();

        return response()->json([
            'status' => true,
            'settings' => [
                'main_title' => $settings->main_title,
                'secondary_title' => $settings->secondary_title,
                'daily_phrase' => $settings->daily_phrase,
                // Cantidad total de miembros registrados (todos)
                'members_count' => Member::count(),
                // Redes activas ordenadas (se muestran por ícono)
                'social_links' => BannerSocialLink::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'type', 'url', 'label', 'icon', 'image_url']),
            ],
        ]);
    }

    /**
     * Actualiza los textos del banner (admin).
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'main_title' => 'nullable|string|max:255',
            'secondary_title' => 'nullable|string|max:255',
            'daily_phrase' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $settings = SiteSetting::current();
        $settings->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => __('settings.updated'),
            'settings' => $settings,
        ]);
    }
}
