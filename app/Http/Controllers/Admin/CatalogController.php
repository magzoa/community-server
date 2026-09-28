<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityRole;
use App\Models\ProfessionalProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    /**
     * Resuelve la clase del modelo según el tipo de catálogo.
     */
    private function modelClass(string $type): ?string
    {
        return match ($type) {
            'community-roles' => CommunityRole::class,
            'professional-profiles' => ProfessionalProfile::class,
            default => null,
        };
    }

    /**
     * Listado (usado por el ABM admin y por el perfil autenticado).
     * ?only_active=1 filtra los activos (para los selects del perfil).
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $class = $this->modelClass($type);
        if (! $class) {
            return response()->json(['status' => false, 'message' => __('catalogs.not_found')], 404);
        }

        $query = $class::query()->orderBy('sort_order')->orderBy('name');
        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'status' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Crea un elemento del catálogo.
     */
    public function store(Request $request, string $type): JsonResponse
    {
        $class = $this->modelClass($type);
        if (! $class) {
            return response()->json(['status' => false, 'message' => __('catalogs.not_found')], 404);
        }

        $data = $this->validateData($request, $class, null);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $item = $class::create($data);

        return response()->json([
            'status' => true,
            'message' => __('catalogs.created'),
            'item' => $item,
        ], 201);
    }

    /**
     * Actualiza un elemento del catálogo.
     */
    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $class = $this->modelClass($type);
        if (! $class) {
            return response()->json(['status' => false, 'message' => __('catalogs.not_found')], 404);
        }

        $item = $class::find($id);
        if (! $item) {
            return response()->json(['status' => false, 'message' => __('catalogs.item_not_found')], 404);
        }

        $data = $this->validateData($request, $class, $item);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $item->update($data);

        return response()->json([
            'status' => true,
            'message' => __('catalogs.updated'),
            'item' => $item,
        ]);
    }

    /**
     * Elimina un elemento. Los miembros relacionados quedan en null
     * (FK nullOnDelete) o se limpia el pivote (cascade).
     */
    public function destroy(string $type, int $id): JsonResponse
    {
        $class = $this->modelClass($type);
        if (! $class) {
            return response()->json(['status' => false, 'message' => __('catalogs.not_found')], 404);
        }

        $item = $class::find($id);
        if (! $item) {
            return response()->json(['status' => false, 'message' => __('catalogs.item_not_found')], 404);
        }

        $item->delete();

        return response()->json([
            'status' => true,
            'message' => __('catalogs.deleted'),
        ]);
    }

    /**
     * Valida y prepara los datos; genera slug único desde el nombre.
     * Devuelve array de datos o un JsonResponse de error 422.
     *
     * @return array<string,mixed>|JsonResponse
     */
    private function validateData(Request $request, string $class, ?Model $item)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|regex:/^#([0-9A-Fa-f]{6})$/',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $data = $validator->validated();

        // Slug único derivado del nombre
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        $i = 1;
        while (
            $class::where('slug', $slug)
                ->when($item, fn ($q) => $q->where('id', '!=', $item->id))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$i++;
        }
        $data['slug'] = $slug;

        return $data;
    }
}
