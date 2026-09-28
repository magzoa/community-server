<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMemberResource extends JsonResource
{
    /**
     * Representación pública de un miembro.
     * email y celular solo se exponen si quien consulta es "member_active".
     */
    public function toArray(Request $request): array
    {
        // ¿El solicitante es un miembro activo (logueado + aprobado)?
        // Se resuelve por el guard sanctum aunque la ruta sea pública:
        // devuelve el usuario si viene un Bearer válido, o null si es anónimo.
        $viewer = auth('sanctum')->user();
        $canSeeContact = $viewer && $viewer->hasRole('member_active');

        return [
            'id' => $this->id,
            'nickname' => $this->whenLoaded('user', fn () => $this->user->nickname),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'avatar_url' => $this->avatar_url,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'bio' => $this->bio,

            // Perfil profesional (catálogo)
            'professional_profile' => $this->whenLoaded('professionalProfile', function () {
                return $this->professionalProfile
                    ? [
                        'name' => $this->professionalProfile->name,
                        'slug' => $this->professionalProfile->slug,
                        'color' => $this->professionalProfile->color,
                    ]
                    : null;
            }),

            // Roles de comunidad (catálogo, N:N)
            'community_roles' => $this->whenLoaded('communityRoles', function () {
                return $this->communityRoles->map(fn ($r) => [
                    'name' => $r->name,
                    'slug' => $r->slug,
                    'color' => $r->color,
                ]);
            }),

            // Redes sociales
            'social_links' => $this->whenLoaded('socialLinks', function () {
                return $this->socialLinks->map(fn ($l) => [
                    'type' => $l->type,
                    'url' => $l->url,
                    'label' => $l->label,
                    'icon' => $l->icon,
                    'image_url' => $l->image_url,
                ]);
            }),

            // Contacto: solo visible para miembros activos
            'contact_email' => $canSeeContact ? $this->contact_email : null,
            'phone' => $canSeeContact ? $this->phone : null,
            'can_see_contact' => $canSeeContact,
        ];
    }
}
