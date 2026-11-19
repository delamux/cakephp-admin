# Subida de archivos

[English](../en/uploads.md) · **Español**

[josegonzalez/cakephp-upload](https://github.com/FriendsOfCake/cakephp-upload) (FriendsOfCake) con
[Flysystem](https://flysystem.thephpleague.com/) 3, envuelto en `App\Storage\Uploads` para que todas las entidades
sigan la misma convención. Primer uso: la **foto de usuario**.

## Convención de nombres

```
<tabla>/<id>/<campo>/<uuid>.<ext>        p. ej. users/0b6f2a4e-…/photo/9f1c…-….webp
```

| Parte | Por qué |
| --- | --- |
| `<tabla>/<id>/` primero | Al borrar el registro se borra su carpeta entera (`Uploads::deleteRecord()`): nada huérfano, cumple el RGPD |
| `<campo>/` | Una entidad puede tener varios archivos (foto, contrato…) sin mezclarse |
| `<uuid>` nuevo en cada subida | Nunca se sobrescribe; la URL cambia y la caché del navegador se invalida sola |
| Nunca el nombre del cliente | Puede llevar datos personales («DNI_Juan_Perez.jpg»), caracteres raros o `../`; su extensión la decide quien sube |
| `<ext>` del tipo real | Las imágenes se reconvierten y siempre son `.webp` |

Quién subió el archivo no va en la ruta (el dueño del registro no siempre es quien sube): queda en la
[actividad de la cuenta](account-activity.md).

## Almacenamiento

- **Privado y fuera de `webroot`**: `Uploads.path` (`UPLOADS_PATH`, por defecto `storage/uploads/`). nginx no los
  sirve: los entrega un controlador que comprueba la sesión, así que la CSP sigue siendo `img-src 'self'`.
- **Producción**: volumen Docker persistente `uploads` montado en `/app/storage/uploads` en `app` y `worker`
  (`compose.prod.yaml`, `compose.coolify.yaml`); Coolify lo conserva entre deploys.
  - **Copias:** el volumen hay que copiarlo **junto con la base de datos** ([Operación](operations.md#copias-de-seguridad)).
  - **Réplicas:** mientras los archivos vivan en un volumen local, **una sola réplica de `app`**. Para escalar, el
    siguiente paso es un adaptador S3 de Flysystem (`league/flysystem-aws-s3-v3`) en `Uploads::imageField()`.
- En dev, `storage/uploads/` está en el repositorio montado (ignorado por git y fuera de la imagen Docker).

## Foto de usuario

| | |
| --- | --- |
| Dónde se cambia | Tarjeta **Photo** del perfil (la propia) y de la edición de usuario (la de otros) |
| Quién | Cada uno la suya; con acceso de administración, la de otros, **nunca la de un superadmin salvo siendo superadmin** (`UserRoleManager::canManage()`) |
| Qué se acepta | JPEG, PNG o WebP **por su contenido real** (`finfo` + `getimagesize`, no la extensión ni el tipo que dice el navegador), hasta 2 MB y 6000 × 6000 px |
| Qué se guarda | Reconvertida con GD a **WebP cuadrado de 256 × 256** (recorte centrado, orientación EXIF aplicada): se eliminan los metadatos EXIF —como la posición GPS— y cualquier contenido escondido en el archivo |
| Al reemplazarla / quitarla | Se borra el archivo anterior; queda «Photo changed / removed» en la actividad, con quién lo hizo |
| Al borrar el usuario | Se borra `users/<id>/` entero |
| Cómo se sirve | `/user-photo/show/<id>?v=<archivo>`: solo con sesión iniciada, `Cache-Control: private, max-age=31536000, immutable` |
| Dónde se ve | Perfil, ficha, listado de usuarios, dashboard y menú lateral (`$this->UserPhoto->url($user)`); sin foto, las iniciales |

La columna `avatar` de CakeDC queda para el login social; la foto subida es `photo` + `photo_dir`, que **no se pueden
asignar desde formularios**: solo las escribe `App\UseCase\Users\ChangeUserPhoto`.

## Añadir un archivo a otra entidad

Imagen (p. ej. el logo de un cliente):

1. Migración con dos columnas de texto: `logo` (nombre) y `logo_dir` (carpeta).
2. En la Table: `Uploads::attachImage($this, 'logo')` en `initialize()` y, en `afterDelete()`,
   `Uploads::deleteRecord($this->getTable(), (string)$entity->get('id'))`.
3. En la entidad, `logo` y `logo_dir` fuera de `$_accessible`.
4. Un caso de uso que valide con `ImageProcessor::validate()`, guarde con
   `patchEntity($entity, ['logo' => $file], ['accessibleFields' => ['logo' => true]])`, llame a
   `ImageProcessor::cleanup()` y borre el archivo anterior con `Uploads::delete()`. `ChangeUserPhoto` es el ejemplo.
5. Un controlador que lo sirva tras comprobar permisos (como `UserPhotoController::show()`).

Otros archivos (PDF, documentos): misma convención y mismos pasos con una configuración propia en `Uploads`
(sin la reconversión de imagen; valida el tipo real con `finfo` y guarda la extensión de ese tipo, nunca la del cliente).
