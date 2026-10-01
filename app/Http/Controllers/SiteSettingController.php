<?php

namespace App\Http\Controllers;

use App\Models\BannerSocialLink;
use App\Models\Member;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                // Apartado Meetup destacado (englobador de eventos)
                'meetup_title' => $settings->meetup_title,
                'meetup_subtitle' => $settings->meetup_subtitle,
                'meetup_description' => $settings->meetup_description,
                'meetup_url' => $settings->meetup_url,
                'meetup_image' => $settings->meetup_image,
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
            'meetup_title' => 'nullable|string|max:255',
            'meetup_subtitle' => 'nullable|string|max:255',
            'meetup_description' => 'nullable|string|max:2000',
            'meetup_url' => 'nullable|url|max:255',
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

    /**
     * Disco de archivos (reusa el de miembros; fácil pasar a s3).
     */
    private function disk(): string
    {
        return env('MEMBER_FILES_DISK', 'public');
    }

    /**
     * Sube/reemplaza la imagen del apartado Meetup (carpeta banner/meetup).
     * Borra la carpeta antes de subir para no acumular.
     */
    public function uploadMeetupImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $disk = $this->disk();
        $folder = 'banner/meetup';

        Storage::disk($disk)->deleteDirectory($folder);

        $ext = $request->file('image')->getClientOriginalExtension() ?: 'png';
        $path = $request->file('image')->storeAs($folder, "meetup.{$ext}", $disk);

        $settings = SiteSetting::current();
        $settings->update(['meetup_image' => Storage::disk($disk)->url($path).'?v='.time()]);

        return response()->json([
            'status' => true,
            'message' => __('settings.image_updated'),
            'settings' => $settings,
        ]);
    }

    /**
     * Elimina la imagen del apartado Meetup.
     */
    public function deleteMeetupImage(): JsonResponse
    {
        Storage::disk($this->disk())->deleteDirectory('banner/meetup');

        $settings = SiteSetting::current();
        $settings->update(['meetup_image' => null]);

        return response()->json([
            'status' => true,
            'message' => __('settings.image_deleted'),
            'settings' => $settings,
        ]);
    }
}
