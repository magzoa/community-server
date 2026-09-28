# Despliegue en producción — community-server (Portainer + AWS EC2)

Stack Docker con tres servicios:

- **app** — Laravel 12 servido por Nginx + PHP-FPM (puerto `80`). Incluye un
  worker de cola (`queue:work`) vía Supervisor.
- **db** — MariaDB 11.4 (puerto `3306`).
- **phpmyadmin** — administración de la BD (puerto `8080`).

Archivos que componen el despliegue:

```
Dockerfile                     → imagen de producción (Nginx + PHP-FPM)
docker-compose.yml             → definición del Stack
.dockerignore                  → qué se excluye de la imagen
.env.production.example        → variables del Stack (copiar y completar)
docker/
  nginx.conf                   → vhost que sirve /public
  supervisord.conf             → php-fpm + nginx + queue worker
  entrypoint.sh                → espera BD, migra, cachea y arranca
```

---

## 1. Puertos a abrir en el Security Group de AWS

| Puerto | Servicio            | Origen sugerido        |
| ------ | ------------------- | ---------------------- |
| 22     | SSH                 | Mi IP                  |
| 80     | App web (Laravel)   | 0.0.0.0/0 (público)    |
| 443    | HTTPS (si usas TLS) | 0.0.0.0/0 (público)    |
| 9443   | Portainer           | Mi IP                  |
| 3306   | MariaDB             | 0.0.0.0/0 (comunidad)  |
| 8080   | phpMyAdmin          | Mi IP (recomendado)    |

> Nota: abriste `3306` a cualquier IP a pedido tuyo por ser un proyecto
> comunitario sin datos sensibles. Aun así, pon una contraseña real en
> `DB_PASSWORD`/`DB_ROOT_PASSWORD`. Si más adelante quieres cerrarlo, basta
> quitar el bloque `ports` de `db` en el compose (la app le sigue hablando por
> la red interna de Docker).

---

## 2. Generar el APP_KEY

En tu máquina (o en la EC2 con PHP), genera una clave y guárdala:

```bash
php artisan key:generate --show
```

Copia el valor `base64:...` para usarlo como `APP_KEY` en el Stack.

---

## 3. Desplegar como Stack en Portainer

1. Sube el proyecto a la EC2 (git clone o subir la carpeta `community-server`).
2. En Portainer: **Stacks → Add stack**.
3. Nombre: `community-server`.
4. Método **Repository** (si está en git) o **Upload** / **Web editor** pegando
   el `docker-compose.yml`.
5. En **Environment variables**, define (ver `.env.production.example`):

   ```
   APP_KEY=base64:...
   APP_NAME=AWS UG CAN
   APP_URL=http://18.231.63.186
   DB_DATABASE=community_aws
   DB_USERNAME=community
   DB_PASSWORD=una-clave-real
   DB_ROOT_PASSWORD=otra-clave-real
   SEED_ON_DEPLOY=true
   CORS_ALLOWED_ORIGIN=http://18.231.63.186:5173
   ```

6. **Deploy the stack**.

El `entrypoint.sh` espera a MariaDB, corre `migrate --force`, siembra la BD si
`SEED_ON_DEPLOY=true`, cachea config/rutas/vistas y arranca todo.

---

## 4. Después del primer despliegue

- Cambia `SEED_ON_DEPLOY` a `false` y vuelve a desplegar (para no re-sembrar).
- Cambia la contraseña del admin inicial del seeder
  (`admin@community.test` / `password`).
- Verifica la web en `http://18.231.63.186` y phpMyAdmin en
  `http://18.231.63.186:8080`.

---

## 5. Cliente Vue (community-client)

Se despliega aparte. Recuerda apuntar su `VITE_API_URL` a la API pública, por
ejemplo `http://18.231.63.186/api`, y que ese origen coincida con
`CORS_ALLOWED_ORIGIN` del servidor.
