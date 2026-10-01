<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Servicio para gestionar los archivos de un miembro en el disco.
 * Reutilizable para operaciones sensibles como renombrar la carpeta
 * cuando cambia el nickname.
 */
class MemberFileService
{
    /**
     * Disco configurable (reusa el de miembros; fácil pasar a s3).
     */
    public function disk(): string
    {
        return env('MEMBER_FILES_DISK', 'public');
    }

    /**
     * Renombra la carpeta del usuario: users/<old> → users/<new>.
     * Estrategia segura: copiar todo, verificar, y solo entonces borrar el origen.
     *
     * - Si no hay carpeta origen (o está vacía): no hace nada, devuelve true.
     * - Si el destino ya existe: aborta (no pisa datos ajenos).
     * - Si alguna copia falla: limpia lo copiado y lanza excepción (no borra origen).
     *
     * @throws RuntimeException si el destino existe o falla la copia/verificación.
     */
    public function renameUserFolder(string $oldNickname, string $newNickname): bool
    {
        if ($oldNickname === $newNickname) {
            return true;
        }

        $disk = Storage::disk($this->disk());
        $from = "users/{$oldNickname}";
        $to = "users/{$newNickname}";

        // Sin carpeta origen o sin archivos: nada que mover
        if (! $disk->exists($from)) {
            return true;
        }

        $files = $disk->allFiles($from);
        if (empty($files)) {
            // Carpeta vacía: la eliminamos y listo
            $disk->deleteDirectory($from);

            return true;
        }

        // El destino no debería existir (nickname único); si existe, abortar
        if ($disk->exists($to)) {
            throw new RuntimeException('La carpeta destino ya existe.');
        }

        // 1) Copiar cada archivo al destino (mismo path relativo)
        $copied = [];
        try {
            foreach ($files as $file) {
                $relative = substr($file, strlen($from) + 1); // ruta dentro de la carpeta
                $target = "{$to}/{$relative}";

                if (! $disk->copy($file, $target)) {
                    throw new RuntimeException("No se pudo copiar {$file}.");
                }

                // 2) Verificar que la copia existe en destino
                if (! $disk->exists($target)) {
                    throw new RuntimeException("La copia no se verificó en {$target}.");
                }

                $copied[] = $target;
            }
        } catch (\Throwable $e) {
            // Falla parcial: limpiar lo copiado y NO tocar el origen
            $disk->deleteDirectory($to);

            throw new RuntimeException('Falló la copia de archivos: '.$e->getMessage());
        }

        // 3) Copia exitosa y verificada → borrar la carpeta origen
        $disk->deleteDirectory($from);

        return true;
    }

    /**
     * Devuelve la URL pública del avatar de un miembro tras el cambio de carpeta,
     * o null si no hay avatar. Busca el archivo dentro de users/<nickname>/perfil.
     */
    public function currentAvatarUrl(string $nickname): ?string
    {
        $disk = Storage::disk($this->disk());
        $folder = "users/{$nickname}/perfil";

        if (! $disk->exists($folder)) {
            return null;
        }

        $files = $disk->files($folder);
        if (empty($files)) {
            return null;
        }

        // Primer archivo de la carpeta perfil (avatar.<ext>)
        return $disk->url($files[0]).'?v='.time();
    }
}
