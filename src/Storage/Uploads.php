<?php
declare(strict_types=1);

namespace App\Storage;

use Cake\Core\Configure;
use Cake\Database\TypeFactory;
use Cake\ORM\Table;
use Cake\Utility\Text;
use FilesystemIterator;
use Josegonzalez\Upload\Database\Type\FileType;
use Josegonzalez\Upload\Model\Behavior\UploadBehavior;
use Psr\Http\Message\UploadedFileInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Uploaded files (josegonzalez/cakephp-upload), private storage outside webroot (`Uploads.path`, a
 * persistent volume in production). One naming convention for every entity:
 *
 *     <table>/<id>/<field>/<uuid>.<ext>      e.g. users/0b6f…/photo/9f1c….webp
 *
 * - never the client's file name (personal data, odd characters, a spoofable extension),
 * - a new uuid per upload (no overwrite, browser caches invalidated),
 * - the record id first, so deleting a record deletes its folder (Uploads::deleteRecord()).
 * Files are served by a controller that checks the session (App\Controller\UserPhotoController).
 */
final class Uploads
{
    /**
     * @return string Absolute root, with trailing separator.
     */
    public static function root(): string
    {
        $path = (string)(Configure::read('Uploads.path') ?: ROOT . DS . 'storage' . DS . 'uploads');

        return rtrim($path, '/\\') . DS;
    }

    /**
     * Makes `$field` of `$table` an image upload (the one call a Table needs):
     * `Uploads::attachImage($this, 'photo')` in `initialize()`. Needs the `<field>` and `<field>_dir`
     * columns (strings).
     *
     * @param \Cake\ORM\Table $table Table.
     * @param string $field Field.
     * @param int $size Side of the square, in pixels.
     * @return void
     */
    public static function attachImage(Table $table, string $field, int $size = 256): void
    {
        // The plugin's column type keeps the UploadedFile on the entity until its behavior stores it
        TypeFactory::map('upload.file', FileType::class);
        $table->getSchema()->setColumnType($field, 'upload.file');
        $table->addBehavior('Upload', ['className' => UploadBehavior::class] + self::imageField($field, $size));
    }

    /**
     * Upload behavior config for an image field: validated elsewhere (ImageProcessor::validate()),
     * re-encoded here as a square WebP (no EXIF, no smuggled payload).
     *
     * @param string $field Field holding the file name (`<field>_dir` holds its directory).
     * @param int $size Side of the square, in pixels.
     * @return array<string, array<string, mixed>>
     */
    public static function imageField(string $field, int $size = 256): array
    {
        return [$field => [
            'path' => '{table}{DS}{primaryKey}{DS}{field}{DS}',
            'filesystem' => ['root' => self::root(), 'visibility' => 'private'],
            // Columns the behavior fills; only `<field>` and `<field>_dir` exist
            'fields' => [
                'dir' => $field . '_dir',
                'size' => $field . '_size',
                'type' => $field . '_type',
                'ext' => $field . '_ext',
            ],
            'nameCallback' => fn(): string => Text::uuid() . '.webp',
            'transformer' => function (
                Table $table,
                mixed $entity,
                UploadedFileInterface $data,
                string $field,
                array $settings,
                string $filename,
            ) use ($size): array {
                $source = (string)$data->getStream()->getMetadata('uri');

                return [ImageProcessor::squareWebp($source, $size) => $filename];
            },
            'keepFilesOnDelete' => false,
        ]];
    }

    /**
     * @param string $dir Directory relative to the root (`<table>/<id>/<field>/`).
     * @param string|null $file File name.
     * @return string|null Absolute path, null when there is no file.
     */
    public static function path(?string $dir, ?string $file): ?string
    {
        if (!$dir || !$file || str_contains($dir . $file, '..')) {
            return null;
        }
        $path = self::root() . $dir . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * @param string|null $dir Directory relative to the root.
     * @param string|null $file File name.
     * @return void
     */
    public static function delete(?string $dir, ?string $file): void
    {
        $path = self::path($dir, $file);
        if ($path !== null) {
            unlink($path);
        }
    }

    /**
     * Removes every file of a record (`<table>/<id>/`), e.g. when it is deleted.
     *
     * @param string $table Table name.
     * @param string $id Record id.
     * @return void
     */
    public static function deleteRecord(string $table, string $id): void
    {
        if ($id === '' || preg_match('/[\/\\\\.]/', $table . $id)) {
            return;
        }
        $dir = self::root() . $table . DS . $id;
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
