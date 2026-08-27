<?php

namespace Tests\Feature\Blog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The endpoint the article editor posts to when an image is dropped into the
 * body.
 *
 * The sanitiser that decides which images survive in saved HTML is covered
 * elsewhere; what was never checked is the upload itself. It has to answer
 * with a root-relative URL — the same shape the sanitiser keeps — and it has
 * to refuse anything that is not an image, since whatever it accepts is
 * served straight back from the public directory.
 */
class BlogBodyImageUploadTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $existingFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Uploads land in the public directory, not in a fake disk: the test
        // notes what was there beforehand so it can clear up after itself
        // even when the controller answers with the wrong path.
        $this->existingFiles = $this->blogFiles();
    }

    protected function tearDown(): void
    {
        foreach (array_diff($this->blogFiles(), $this->existingFiles) as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /** @return array<int, string> */
    private function blogFiles(): array
    {
        return glob(public_path('images/blog/*')) ?: [];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function upload(UploadedFile $file)
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/admin/blog/images', ['file' => $file]);

        return $response;
    }

    public function test_an_uploaded_image_comes_back_as_a_root_relative_url(): void
    {
        // The body sanitiser keeps root-relative sources and strips absolute
        // ones, so this is the only shape the editor can insert.
        $response = $this->upload(UploadedFile::fake()->image('terrain.jpg'))->assertOk();

        $url = $response->json('url');

        $this->assertStringStartsWith('/images/blog/blog-body-', $url);
        $this->assertStringEndsWith('.jpg', $url);
    }

    public function test_the_file_is_written_where_the_url_says_it_is(): void
    {
        $url = $this->upload(UploadedFile::fake()->image('terrain.jpg'))->assertOk()->json('url');

        $this->assertFileExists(public_path(ltrim($url, '/')));
    }

    public function test_two_uploads_of_the_same_name_do_not_overwrite_each_other(): void
    {
        $first = $this->upload(UploadedFile::fake()->image('terrain.jpg'))->assertOk()->json('url');
        $second = $this->upload(UploadedFile::fake()->image('terrain.jpg'))->assertOk()->json('url');

        $this->assertNotSame($first, $second);
        $this->assertFileExists(public_path(ltrim($first, '/')));
        $this->assertFileExists(public_path(ltrim($second, '/')));
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/blog/images', ['file' => UploadedFile::fake()->create('payload.pdf', 12, 'application/pdf')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_an_oversized_image_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/blog/images', ['file' => UploadedFile::fake()->image('huge.jpg')->size(9000)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_posting_nothing_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/blog/images', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_a_customer_cannot_upload_into_the_public_directory(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/admin/blog/images', ['file' => UploadedFile::fake()->image('terrain.jpg')])
            ->assertStatus(302);
    }
}
