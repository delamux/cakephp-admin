<?php
declare(strict_types=1);

namespace App\Storage;

use finfo;
use GdImage;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Image uploads with GD (no extra packages): validation by the real content (not the extension or
 * the type the browser claims) and re-encoding to a square WebP, which drops EXIF (GPS position…)
 * and anything smuggled inside the file. JPEG orientation is applied before EXIF is lost.
 */
final class ImageProcessor
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_PIXELS = 6000;
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @var list<string> Temporary files written by squareWebp() in this request.
     */
    private static array $tmpFiles = [];

    /**
     * @param \Psr\Http\Message\UploadedFileInterface $file Upload.
     * @return string|null Error for the user, null when it is a valid image.
     */
    public static function validate(UploadedFileInterface $file): ?string
    {
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return __('The photo cannot be larger than 2 MB.');
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return __('The photo could not be uploaded. Try again.');
        }
        if ((int)$file->getSize() > self::MAX_BYTES) {
            return __('The photo cannot be larger than 2 MB.');
        }
        $path = (string)$file->getStream()->getMetadata('uri');
        $type = is_file($path) ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($path) : '';
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- a broken file is just "not an image"
        $info = $type !== '' ? @getimagesize($path) : false;
        if (!in_array($type, self::TYPES, true) || $info === false) {
            return __('The photo must be a JPEG, PNG or WebP image.');
        }
        if ($info[0] > self::MAX_PIXELS || $info[1] > self::MAX_PIXELS) {
            return __('The photo cannot be larger than {0} × {0} pixels.', self::MAX_PIXELS);
        }

        return null;
    }

    /**
     * Centre-cropped square of `$size` px, as WebP, in a temporary file (see cleanup()).
     *
     * @param string $source Image file (already validated).
     * @param int $size Side in pixels.
     * @return string Temporary file.
     */
    public static function squareWebp(string $source, int $size): string
    {
        $size = max(1, $size);
        $image = imagecreatefromstring((string)file_get_contents($source));
        if (!$image instanceof GdImage) {
            throw new RuntimeException('Not an image.');
        }
        $image = self::orient($image, $source);
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $square = imagecreatetruecolor($size, $size);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagecopyresampled(
            $square,
            $image,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            $size,
            $size,
            $side,
            $side,
        );
        $tmp = (string)tempnam(sys_get_temp_dir(), 'img');
        if (!imagewebp($square, $tmp, 85)) {
            throw new RuntimeException('Could not encode the image.');
        }
        self::$tmpFiles[] = $tmp;

        return $tmp;
    }

    /**
     * Deletes the temporary files of squareWebp() (after the upload has been stored).
     *
     * @return void
     */
    public static function cleanup(): void
    {
        foreach (self::$tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        self::$tmpFiles = [];
    }

    /**
     * Phone photos are stored sideways with an EXIF orientation flag: apply it (exif extension).
     *
     * @param \GdImage $image Image.
     * @param string $source File.
     * @return \GdImage
     */
    private static function orient(GdImage $image, string $source): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- missing or broken EXIF: no rotation
        $exif = @exif_read_data($source);
        $angle = match ((int)($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
