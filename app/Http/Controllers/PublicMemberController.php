<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicMemberResource;
use App\Models\CommunityRole;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMemberController extends Controller
{
    /**
     * Listado público de miembros aprobados, filtrable por rol de comunidad
     * (?community_role=slug) y paginado (por defecto 6 por página).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 6);
        $perPage = max(1, min($perPage, 24)); // tope de seguridad

        $query = Member::query()
            ->where('status', Member::STATUS_APPROVED)
            ->with([
                'user:id,nickname',
                'professionalProfile',
                'communityRoles',
                'socialLinks',
            ]);

        // Filtro por rol de comunidad (slug)
        $roleSlug = $request->query('community_role');
        if ($roleSlug) {
            $query->whereHas('communityRoles', function ($q) use ($roleSlug) {
                $q->where('slug', $roleSlug);
            });
        }

        $members = $query->orderBy('first_name')->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => PublicMemberResource::collection($members->items()),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    /**
     * Roles de comunidad activos (para los chips de filtro del home).
     */
    public function communityRoles(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => CommunityRole::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'color']),
        ]);
    }
}
