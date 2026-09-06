<?php

namespace Tests\Feature\Admin;

use App\Support\SiteBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * The scheduled archive: the database alone, taken often, pruned to a day
 * of history - and never allowed to throw away a full archive somebody
 * took by hand.
 */
class DatabaseBackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        // Never the real directory: this test deletes what it finds.
        $this->directory = storage_path('framework/testing/backups-'.uniqid());
        mkdir($this->directory, 0755, true);

        // The suite runs on an in-memory database, which has no file to
        // copy; the archive reads this path and nothing else.
        $this->database = $this->directory.'/source.sqlite';
        file_put_contents($this->database, 'SQLite format 3');

        config([
            'backup.directory' => $this->directory,
            'backup.sources' => [],
            'database.connections.'.config('database.default').'.database' => $this->database,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            unlink($path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function test_the_scheduled_archive_holds_the_database_and_says_so(): void
    {
        $name = SiteBackup::createDatabase();

        $this->assertMatchesRegularExpression('/^armooutdoor-db-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $name);

        $zip = new ZipArchive;
        $zip->open($this->directory.'/'.$name);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();

        // The database, and nothing else: the images are what make a full
        // archive too heavy for a quarter-hourly rhythm.
        $this->assertNotEmpty($entries);
        foreach ($entries as $entry) {
            $this->assertStringStartsWith('database/', $entry);
        }

        $listed = SiteBackup::all()->firstWhere('name', $name);
        $this->assertSame('database', $listed['kind']);
        $this->assertNotNull(SiteBackup::path($name));
    }

    public function test_pruning_keeps_the_window_and_spares_what_was_made_by_hand(): void
    {
        // A full archive taken by hand, then four scheduled ones.
        touch($this->directory.'/armooutdoor-2026-09-01-090000.zip');
        foreach (['0800', '0815', '0830', '0845'] as $index => $time) {
            $path = $this->directory.'/armooutdoor-db-2026-09-06-'.$time.'00.zip';
            touch($path, strtotime('2026-09-06 08:00') + $index * 900);
        }

        $this->assertSame(2, SiteBackup::pruneDatabaseArchives(keep: 2));

        $names = SiteBackup::all()->pluck('name');

        // The two newest scheduled ones survive, and the hand-made archive
        // is never a candidate.
        $this->assertContains('armooutdoor-db-2026-09-06-084500.zip', $names);
        $this->assertContains('armooutdoor-db-2026-09-06-083000.zip', $names);
        $this->assertNotContains('armooutdoor-db-2026-09-06-080000.zip', $names);
        $this->assertContains('armooutdoor-2026-09-01-090000.zip', $names);
    }

    public function test_the_command_writes_an_archive(): void
    {
        $this->artisan('backup:database')->assertSuccessful();

        $this->assertCount(1, SiteBackup::all());
    }
}
