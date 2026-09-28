<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BannerSocialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BannerSocialLinkController extends Controller
{
    /**
     * Lista todas las redes del banner (admin).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => BannerSocialLink::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /**
     * Crea una red del banner.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $link = BannerSocialLink::create($data);

        return response()->json([
            'status' => true,
            'message' => __('settings.link_created'),
            'item' => $link,
        ], 201);
    }

    /**
     * Actualiza una red del banner.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $link = BannerSocialLink::find($id);
        if (! $link) {
            return response()->json(['status' => false, 'message' => __('settings.link_not_found')], 404);
        }

        $data = $this->validateData($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $link->update($data);

        return response()->json([
            'status' => true,
            'message' => __('settings.link_updated'),
            'item' => $link,
        ]);
    }

    /**
     * Elimina una red (su imagen se borra por el hook del modelo).
     */
    public function destroy(int $id): JsonResponse
    {
        $link = BannerSocialLink::find($id);
        if (! $link) {
            return response()->json(['status' => false, 'message' => __('settings.link_not_found')], 404);
        }

        $link->delete();

        return response()->json([
            'status' => true,
            'message' => __('settings.link_deleted'),
        ]);
    }

    /**
     * Sube/reemplaza la imagen de una red, en banner/social/<id>-<slug>.
     * Borra el contenido anterior de esa carpeta.
     */
    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $link = BannerSocialLink::find($id);
        if (! $link) {
            return response()->json(['status' => false, 'message' => __('settings.link_not_found')], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $disk = BannerSocialLink::disk();
        $folder = $link->imageFolder();

        // Limpia la carpeta antes de subir (no acumula)
        Storage::disk($disk)->deleteDirectory($folder);

        $ext = $request->file('image')->getClientOriginalExtension() ?: 'png';
        $path = $request->file('image')->storeAs($folder, "image.{$ext}", $disk);

        $link->update(['image_url' => Storage::disk($disk)->url($path).'?v='.time()]);

        return response()->json([
            'status' => true,
            'message' => __('settings.image_updated'),
            'item' => $link,
        ]);
    }

    /**
     * Elimina la imagen de una red.
     */
    public function deleteImage(int $id): JsonResponse
    {
        $link = BannerSocialLink::find($id);
        if (! $link) {
            return response()->json(['status' => false, 'message' => __('settings.link_not_found')], 404);
        }

        Storage::disk(BannerSocialLink::disk())->deleteDirectory($link->imageFolder());
        $link->update(['image_url' => null]);

        return response()->json([
            'status' => true,
            'message' => __('settings.image_deleted'),
            'item' => $link,
        ]);
    }

    /**
     * Valida los datos de una red. Devuelve array o JsonResponse de error.
     *
     * @return array<string,mixed>|JsonResponse
     */
    private function validateData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(BannerSocialLink::TYPES)],
            'url' => 'required|url|max:255',
            'label' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        return $validator->validated();
    }
}
