<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\SiteBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/** Backing the site up, from the admin. */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtures = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearBackups();

        // A handful of files rather than the whole catalogue: the archive is
        // built for real, so pointing it at 46 MB of photographs would make
        // every test in this file take seconds.
        $this->fixtures = storage_path('app/private/backup-fixtures');
        @mkdir($this->fixtures.'/nested', 0755, true);
        file_put_contents($this->fixtures.'/photo.webp', 'not really a photo');
        file_put_contents($this->fixtures.'/nested/invoice.pdf', '%PDF-1.4');

        config(['backup.sources' => [
            'storage/app/private/backup-fixtures' => 'images',
        ]]);
    }

    protected function tearDown(): void
    {
        $this->clearBackups();

        foreach (['/nested/invoice.pdf', '/photo.webp'] as $file) {
            @unlink($this->fixtures.$file);
        }
        @rmdir($this->fixtures.'/nested');
        @rmdir($this->fixtures);

        parent::tearDown();
    }

    /** The archives are real files on disk, so each test starts from none. */
    private function clearBackups(): void
    {
        foreach (glob(storage_path('app/private/'.SiteBackup::DIRECTORY.'/*.zip')) ?: [] as $file) {
            unlink($file);
        }
    }

    public function test_the_owner_can_write_a_backup(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/backups')
            ->assertRedirect('/admin/backups')
            ->assertSessionHas('status');

        $this->assertCount(1, SiteBackup::all());
    }

    public function test_the_archive_holds_the_database_and_the_uploaded_files(): void
    {
        SiteBackup::create();

        $zip = new ZipArchive;
        $zip->open(SiteBackup::path(SiteBackup::all()->first()['name']));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        // What was uploaded, folders and all.
        $this->assertContains('images/photo.webp', $names);
        $this->assertContains('images/nested/invoice.pdf', $names);
    }

    public function test_an_archive_never_swallows_the_ones_before_it(): void
    {
        // The private folder is a source in production, and the archives live
        // inside it: without the exclusion each backup would carry the ones
        // before it and double in size every time.
        config(['backup.sources' => ['storage/app/private' => 'private']]);

        SiteBackup::create();
        $second = SiteBackup::create();

        $zip = new ZipArchive;
        $zip->open(SiteBackup::path($second));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        // Otherwise each backup would be twice the size of the one before.
        $this->assertNotEmpty($names, 'The archive picked up nothing at all.');
        $this->assertEmpty(preg_grep('#'.SiteBackup::DIRECTORY.'/#', $names));
    }

    public function test_the_list_shows_what_has_been_taken(): void
    {
        $name = SiteBackup::create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee($name)
            ->assertSee('Download')
            ->assertDontSee('No backup yet.');
    }

    public function test_an_empty_list_says_so(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('No backup yet.');
    }

    public function test_a_backup_can_be_downloaded(): void
    {
        $name = SiteBackup::create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/backups/'.$name)
            ->assertOk()
            ->assertDownload($name);
    }

    public function test_a_backup_can_be_deleted_and_asks_first(): void
    {
        $name = SiteBackup::create();
        $owner = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('data-modal-open="backup-delete-modal"', false)
            ->assertSee('Delete this backup?');

        $this->actingAs($owner)
            ->delete('/admin/backups/'.$name)
            ->assertRedirect('/admin/backups');

        $this->assertNull(SiteBackup::path($name));
        $this->assertCount(0, SiteBackup::all());
    }

    public function test_a_name_that_walks_out_of_the_directory_is_refused(): void
    {
        SiteBackup::create();
        $owner = User::factory()->admin()->create();

        // The name arrives from a URL: it is checked, not trusted.
        // Four levels up from the backup directory is the project root, where
        // .env sits: a name that walks there must not be served.
        $this->assertFileExists(base_path('.env'));

        foreach (['../../../../.env', 'armooutdoor-2026-01-01-000000.zip/../../../../.env', 'anything.zip'] as $name) {
            $this->assertNull(SiteBackup::path($name));
            $this->actingAs($owner)->get('/admin/backups/'.$name)->assertNotFound();
        }
    }

    public function test_the_archives_live_outside_the_public_directory(): void
    {
        $name = SiteBackup::create();

        // A backup holds every order and every customer's address.
        $this->assertStringStartsWith(storage_path('app/private'), SiteBackup::path($name));
        $this->assertFileDoesNotExist(public_path(SiteBackup::DIRECTORY.'/'.$name));
    }

    public function test_only_the_owner_reaches_any_of_it(): void
    {
        $name = SiteBackup::create();

        $this->actingAs(User::factory()->staffAdmin()->create())->get('/admin/backups')->assertForbidden();
        $this->actingAs(User::factory()->staffAdmin()->create())->post('/admin/backups')->assertForbidden();
        $this->actingAs(User::factory()->staffAdmin()->create())->get('/admin/backups/'.$name)->assertForbidden();

        auth()->logout();
        $this->get('/admin/backups')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/backups')->assertRedirect();
    }

    public function test_the_menu_offers_backups_to_the_owner_alone(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Backups')
            ->assertSee('/admin/backups', false);

        $this->actingAs(User::factory()->staffAdmin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Backups');
    }

    public function test_the_database_file_goes_in_when_there_is_one(): void
    {
        // The suite runs on an in-memory database, so this is checked against
        // a real file rather than the connection of the moment.
        $database = storage_path('app/private/backup-fixtures/base.sqlite');
        file_put_contents($database, 'SQLite format 3');

        $default = config('database.default');
        config(['database.connections.backup-test' => ['driver' => 'sqlite', 'database' => $database]]);
        config(['database.default' => 'backup-test']);

        $name = SiteBackup::create();

        // Put the connection back at once: the suite runs in a transaction on
        // the real one, and it has to be there to roll back at the end.
        config(['database.default' => $default]);

        $zip = new ZipArchive;
        $zip->open(SiteBackup::path($name));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('database/base.sqlite', $names);

        @unlink($database);
    }
}
