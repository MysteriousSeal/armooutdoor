<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminChangelogTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_shows_a_build_number_for_a_versioned_release(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.changelog'))
            ->assertOk()
            ->assertSee('v0.2.8')
            ->assertSee('changelog-build', false);
    }

    public function test_parses_a_release_header_with_version_and_build(): void
    {
        $original = File::get(base_path('CHANGELOG.md'));

        File::put(base_path('CHANGELOG.md'), <<<'MD'
        # Changelog

        ## 2026-08-20 — v9.9.9 — build ABC123

        ### Admin

        - Did a thing.
        MD);

        try {
            $admin = User::factory()->admin()->create();

            $this->actingAs($admin)
                ->get(route('admin.changelog'))
                ->assertOk()
                ->assertSee('v9.9.9')
                ->assertSee('ABC123')
                ->assertSee('Did a thing.');
        } finally {
            File::put(base_path('CHANGELOG.md'), $original);
        }
    }

    public function test_parses_a_release_header_with_only_a_build_number(): void
    {
        $original = File::get(base_path('CHANGELOG.md'));

        File::put(base_path('CHANGELOG.md'), <<<'MD'
        # Changelog

        ## 2026-08-14 — build ZZZ999

        ### Admin

        - An early, unversioned release.
        MD);

        try {
            $admin = User::factory()->admin()->create();

            $response = $this->actingAs($admin)->get(route('admin.changelog'));

            $response->assertOk()
                ->assertSee('ZZZ999')
                ->assertSee('An early, unversioned release.')
                ->assertDontSee('changelog-version', false);
        } finally {
            File::put(base_path('CHANGELOG.md'), $original);
        }
    }
}
