<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageAdvertisement extends Model
{
    public const STORAGE_DIRECTORY = 'homepage-advertisements';

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected $fillable = [
        'image_path',
        'alt_text',
    ];

    public static function isManagedImagePath(mixed $path): bool
    {
        if (! is_string($path) || $path === '' || $path !== trim($path)) {
            return false;
        }

        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        $segments = explode('/', $path);

        if (count($segments) !== 2 || $segments[0] !== self::STORAGE_DIRECTORY) {
            return false;
        }

        $filename = $segments[1];

        if (
            $filename === ''
            || $filename === '.'
            || $filename === '..'
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D', $filename) !== 1
        ) {
            return false;
        }

        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true);
    }
}
