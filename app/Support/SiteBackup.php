<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * A copy of everything the code cannot recreate.
 *
 * The database, the uploaded images and the private files — product
 * photographs, blog covers, attached invoices. The code itself is in git and
 * is left out: backing it up would double the size of every archive for
 * something already kept elsewhere.
 *
 * Archives live outside public/, like the invoices: a backup holds the whole
 * catalogue, every order and every customer's address.
 */
class SiteBackup
{
    /** Where the archives are kept, relative to storage/app/private. */
    public const DIRECTORY = 'backups';

    /**
     * What goes in, as [path relative to the project => folder in the archive].
     *
     * Read from config rather than fixed here, so a test can point it at a
     * handful of files instead of zipping the whole catalogue.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        return (array) config('backup.sources', []);
    }

    /**
     * Writes a new archive and returns its filename.
     *
     * Named for the moment it was taken, down to the minute, so the list
     * sorts itself and two backups of the same day do not collide.
     */
    public static function create(): string
    {
        $directory = self::directory();

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $name = 'armooutdoor-'.CarbonImmutable::now()->format('Y-m-d-His').'.zip';
        $path = $directory.'/'.$name;

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The backup file could not be opened for writing.');
        }

        self::addDatabase($zip);

        foreach (self::sources() as $source => $folder) {
            self::addDirectory($zip, base_path($source), $folder);
        }

        $zip->close();

        return $name;
    }

    /**
     * The archives already taken, newest first.
     *
     * @return Collection<int, array{name: string, size: int, taken_at: CarbonImmutable}>
     */
    public static function all(): Collection
    {
        $directory = self::directory();

        if (! is_dir($directory)) {
            return collect();
        }

        return collect(glob($directory.'/*.zip') ?: [])
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'taken_at' => CarbonImmutable::createFromTimestamp(filemtime($path)),
            ])
            ->sortByDesc('taken_at')
            ->values();
    }

    /**
     * The full path of one archive, or null if the name does not name one.
     *
     * The name is checked rather than trusted: it arrives from a URL, and a
     * path could otherwise be walked out of the backup directory.
     */
    public static function path(string $name): ?string
    {
        if (preg_match('/^armooutdoor-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $name) !== 1) {
            return null;
        }

        $path = self::directory().'/'.$name;

        return is_file($path) ? $path : null;
    }

    public static function delete(string $name): bool
    {
        $path = self::path($name);

        return $path !== null && unlink($path);
    }

    /** What the archives take up together. */
    public static function totalSize(): int
    {
        return (int) self::all()->sum('size');
    }

    /**
     * The database file itself.
     *
     * SQLite is one file, so it is copied whole. Another driver would need to
     * be dumped instead, and this is where that would go.
     */
    private static function addDatabase(ZipArchive $zip): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if (is_string($database) && is_file($database)) {
            $zip->addFile($database, 'database/'.basename($database));
        }
    }

    private static function addDirectory(ZipArchive $zip, string $path, string $folder): void
    {
        if (! is_dir($path)) {
            return;
        }

        // The backup directory is skipped: an archive must not swallow the
        // archives taken before it.
        $files = Finder::create()
            ->files()
            ->in($path)
            ->exclude(self::DIRECTORY)
            ->ignoreDotFiles(false)
            ->ignoreVCS(true);

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $zip->addFile($file->getRealPath(), $folder.'/'.$file->getRelativePathname());
        }
    }

    private static function directory(): string
    {
        return storage_path('app/private/'.self::DIRECTORY);
    }
}
