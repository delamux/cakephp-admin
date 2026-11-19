# File uploads

**English** · [Español](../es/uploads.md)

[josegonzalez/cakephp-upload](https://github.com/FriendsOfCake/cakephp-upload) (FriendsOfCake) with
[Flysystem](https://flysystem.thephpleague.com/) 3, wrapped in `App\Storage\Uploads` so every entity follows the
same convention. First use: the **user photo**.

## Naming convention

```
<table>/<id>/<field>/<uuid>.<ext>        e.g. users/0b6f2a4e-…/photo/9f1c…-….webp
```

| Part | Why |
| --- | --- |
| `<table>/<id>/` first | Deleting the record deletes its whole folder (`Uploads::deleteRecord()`): nothing orphaned, GDPR-compliant |
| `<field>/` | An entity can have several files (photo, contract…) without mixing them |
| New `<uuid>` on every upload | Nothing is ever overwritten; the URL changes and the browser cache invalidates itself |
| Never the client's file name | It may contain personal data ("Passport_John_Smith.jpg"), odd characters or `../`; its extension is chosen by whoever uploads it |
| `<ext>` from the real type | Images are re-encoded and are always `.webp` |

Who uploaded the file isn't part of the path (the record's owner isn't always the uploader): it's recorded in the
[account activity](account-activity.md).

## Storage

- **Private and outside `webroot`**: `Uploads.path` (`UPLOADS_PATH`, default `storage/uploads/`). nginx doesn't
  serve them: a controller that checks the session delivers them, so the CSP stays `img-src 'self'`.
- **Production**: persistent Docker volume `uploads` mounted at `/app/storage/uploads` on `app` and `worker`
  (`compose.prod.yaml`, `compose.coolify.yaml`); Coolify keeps it across deploys.
  - **Backups:** the volume must be backed up **together with the database** ([Operations](operations.md#backups)).
  - **Replicas:** while files live on a local volume, **a single `app` replica**. To scale, the next step is a
    Flysystem S3 adapter (`league/flysystem-aws-s3-v3`) in `Uploads::imageField()`.
- In dev, `storage/uploads/` lives in the mounted repository (ignored by git and excluded from the Docker image).

## User photo

| | |
| --- | --- |
| Where it's changed | **Photo** card on the profile (your own) and on the user edit page (someone else's) |
| Who | Everyone their own; with admin access, other users' too, **never a superadmin's unless you're a superadmin** (`UserRoleManager::canManage()`) |
| What's accepted | JPEG, PNG or WebP **by their actual content** (`finfo` + `getimagesize`, not the extension or the type reported by the browser), up to 2 MB and 6000 × 6000 px |
| What's stored | Re-encoded with GD as a **256 × 256 square WebP** (centred crop, EXIF orientation applied): EXIF metadata —such as the GPS position— and any content hidden in the file are removed |
| When replaced / removed | The previous file is deleted; "Photo changed / removed" is recorded in the activity, along with who did it |
| When the user is deleted | The whole `users/<id>/` is deleted |
| How it's served | `/user-photo/show/<id>?v=<file>`: only with an active session, `Cache-Control: private, max-age=31536000, immutable` |
| Where it's shown | Profile, user page, users list, dashboard and sidebar (`$this->UserPhoto->url($user)`); without a photo, the initials |

CakeDC's `avatar` column is kept for social login; the uploaded photo is `photo` + `photo_dir`, which **can't be
assigned from forms**: only `App\UseCase\Users\ChangeUserPhoto` writes them.

## Adding a file to another entity

Image (e.g. a customer's logo):

1. Migration with two text columns: `logo` (name) and `logo_dir` (folder).
2. In the Table: `Uploads::attachImage($this, 'logo')` in `initialize()` and, in `afterDelete()`,
   `Uploads::deleteRecord($this->getTable(), (string)$entity->get('id'))`.
3. In the entity, `logo` and `logo_dir` kept out of `$_accessible`.
4. A use case that validates with `ImageProcessor::validate()`, saves with
   `patchEntity($entity, ['logo' => $file], ['accessibleFields' => ['logo' => true]])`, calls
   `ImageProcessor::cleanup()` and deletes the previous file with `Uploads::delete()`. `ChangeUserPhoto` is the
   example.
5. A controller that serves it after checking permissions (like `UserPhotoController::show()`).

Other files (PDFs, documents): same convention and same steps with their own configuration in `Uploads` (without the
image re-encoding; validate the real type with `finfo` and store that type's extension, never the client's).
