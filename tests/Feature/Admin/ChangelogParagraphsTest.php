<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce que la page Changelog montre du fichier CHANGELOG.md.
 *
 * Une entrée n'est pas faite que de puces : un point long porte souvent un
 * second paragraphe en retrait, et une section se termine souvent sur une
 * mention « Fixed » à part. Le lecteur ne lisait ni l'un ni l'autre : ils
 * étaient parcourus puis jetés, et la page montrait moins que le fichier.
 */
class ChangelogParagraphsTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/changelog')
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_still_renders(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/changelog')->assertOk();
    }

    public function test_a_paragraph_under_a_bullet_is_shown(): void
    {
        $this->assertStringContainsString(
            'Stock is allowed to go negative rather than blocking the validation',
            $this->page()
        );
    }

    public function test_a_fixed_note_closing_a_section_is_shown(): void
    {
        // Ces mentions sont à ras de marge, sans tiret : le lecteur les
        // ignorait entièrement.
        $this->assertStringContainsString(
            'the coloured chips in list headers had no colour in light mode',
            $this->page()
        );
    }

    public function test_every_paragraph_of_the_file_reaches_the_page(): void
    {
        $plain = html_entity_decode(strip_tags($this->page()), ENT_QUOTES | ENT_HTML5);
        $lines = preg_split('/\R/', (string) file_get_contents(base_path('CHANGELOG.md'))) ?: [];
        $inRelease = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '## ')) {
                $inRelease = true;

                continue;
            }

            if (! $inRelease || trim($line) === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Le texte nu, débarrassé des marqueurs Markdown, doit se retrouver
            // dans le texte de la page. La comparaison se fait hors balises :
            // le gras coupe la phrase en deux au milieu du HTML.
            $text = preg_replace('/^\s*-\s*/', '', $line) ?? $line;
            $text = str_replace(['**', '`'], '', trim($text));

            $this->assertStringContainsString(
                mb_substr($text, 0, 60),
                $plain,
                'perdu : '.mb_substr($text, 0, 60)
            );
        }
    }

    public function test_the_paragraphs_carry_their_own_classes(): void
    {
        $html = $this->page();

        // Une puce et une suite de paragraphe ne se lisent pas pareil : sans
        // classe distincte, la suite hériterait de la puce.
        $this->assertStringContainsString('changelog-item-paragraph', $html);
        $this->assertStringContainsString('changelog-note', $html);
    }

    public function test_bold_still_renders_inside_them(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression('/changelog-note[^>]*>\s*<strong>Fixed:<\/strong>/', $html);
    }

    public function test_the_file_header_is_not_taken_for_a_note(): void
    {
        // La phrase d'introduction précède la première version : elle n'a pas
        // à devenir la note d'une section.
        $this->assertStringNotContainsString(
            'All notable changes to this project',
            $this->page()
        );
    }
}
