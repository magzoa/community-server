<?php

use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\BannerSocialLinkController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PublicMemberController;
use App\Http\Controllers\SiteSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Módulo Comunidad (fase 1)
|--------------------------------------------------------------------------
*/

// ─── Público ────────────────────────────────────────────────────────────────
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::get('/check-nickname', [RegisterController::class, 'checkNickname']);

// Miembros públicos (home): aprobados, filtro por rol, paginado
Route::get('/public/members', [PublicMemberController::class, 'index']);
Route::get('/public/community-roles', [PublicMemberController::class, 'communityRoles']);

// Configuración pública del banner (textos + count + redes activas)
Route::get('/public/site-settings', [SiteSettingController::class, 'showPublic']);

// ─── Autenticado (cualquier miembro con token) ───────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Sesión
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/user', [LoginController::class, 'me']);

    // Catálogos para los selects del perfil (activos)
    Route::get('/catalogs/{type}', [CatalogController::class, 'index'])
        ->where('type', 'community-roles|professional-profiles');

    // Perfil propio
    Route::get('/member/profile', [MemberController::class, 'showProfile']);
    Route::put('/member/profile', [MemberController::class, 'updateProfile']);

    // Foto de perfil
    Route::post('/member/avatar', [MemberController::class, 'uploadAvatar']);
    Route::delete('/member/avatar', [MemberController::class, 'deleteAvatar']);

    // Redes propias (se permiten duplicados de tipo)
    Route::post('/member/social-links', [MemberController::class, 'addSocialLink']);
    Route::put('/member/social-links/{id}', [MemberController::class, 'updateSocialLink'])->where('id', '[0-9]+');
    Route::delete('/member/social-links/{id}', [MemberController::class, 'deleteSocialLink'])->where('id', '[0-9]+');

    // ─── Solo miembros aprobados (funciones internas, fase futura) ────────────
    Route::middleware('role:member_active')->group(function () {
        // Aquí irán las rutas de comunidad (publicaciones, etc.)
    });

    // ─── Solo admin ───────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/roles', [AdminMemberController::class, 'availableRoles']);
        Route::get('/admin/members', [AdminMemberController::class, 'index']);
        Route::get('/admin/members/{id}', [AdminMemberController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/admin/members/{id}/approve', [AdminMemberController::class, 'approve'])->where('id', '[0-9]+');
        Route::post('/admin/members/{id}/reject', [AdminMemberController::class, 'reject'])->where('id', '[0-9]+');
        Route::put('/admin/members/{id}/roles', [AdminMemberController::class, 'updateRoles'])->where('id', '[0-9]+');
        Route::put('/admin/members/{id}/catalogs', [AdminMemberController::class, 'updateCatalogs'])->where('id', '[0-9]+');
        Route::delete('/admin/members/{id}', [AdminMemberController::class, 'destroy'])->where('id', '[0-9]+');

        // ABM de catálogos (roles de comunidad / perfiles profesionales)
        Route::get('/admin/catalogs/{type}', [CatalogController::class, 'index'])
            ->where('type', 'community-roles|professional-profiles');
        Route::post('/admin/catalogs/{type}', [CatalogController::class, 'store'])
            ->where('type', 'community-roles|professional-profiles');
        Route::put('/admin/catalogs/{type}/{id}', [CatalogController::class, 'update'])
            ->where('type', 'community-roles|professional-profiles')->where('id', '[0-9]+');
        Route::delete('/admin/catalogs/{type}/{id}', [CatalogController::class, 'destroy'])
            ->where('type', 'community-roles|professional-profiles')->where('id', '[0-9]+');

        // Banner del home: textos + redes
        Route::put('/admin/site-settings', [SiteSettingController::class, 'update']);
        Route::get('/admin/banner-social-links', [BannerSocialLinkController::class, 'index']);
        Route::post('/admin/banner-social-links', [BannerSocialLinkController::class, 'store']);
        Route::put('/admin/banner-social-links/{id}', [BannerSocialLinkController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/admin/banner-social-links/{id}', [BannerSocialLinkController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/admin/banner-social-links/{id}/image', [BannerSocialLinkController::class, 'uploadImage'])->where('id', '[0-9]+');
        Route::delete('/admin/banner-social-links/{id}/image', [BannerSocialLinkController::class, 'deleteImage'])->where('id', '[0-9]+');
    });
});
