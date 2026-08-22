<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.changelog', [
            'releases' => $this->parse((string) file_get_contents(base_path('CHANGELOG.md'))),
        ]);
    }

    /**
     * Parses this project's CHANGELOG.md ("## date — vX.Y.Z — build XXXXXX" releases,
     * "### Category" sections, "- " items with optional "  - " nested notes) into a
     * plain structure for the admin changelog page. Item text is pre-rendered to safe
     * HTML (bold/code).
     *
     * An entry is not only bullets. A long item often carries a second paragraph
     * indented beneath it, and a section often ends on a "**Fixed:**" paragraph of
     * its own. Both were read and dropped, so the page quietly showed less than
     * the file said.
     *
     * @return array<int, array{date: string, version: ?string, build: ?string, categories: array<int, array{name: ?string, items: array<int, array{text: string, paragraphs: array<int, string>, children: array<int, string>}>, notes: array<int, string>}>}>
     */
    private function parse(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $releases = [];
        $release = null;
        $categoryIndex = null;
        $itemIndex = null;

        foreach ($lines as $line) {
            if (preg_match('/^## (.+)$/', $line, $m)) {
                if ($release !== null) {
                    $releases[] = $release;
                }

                $date = $m[1];
                $version = null;
                $build = null;

                if (preg_match('/^(.*) — build (\S+)$/u', $date, $bm)) {
                    $date = trim($bm[1]);
                    $build = trim($bm[2]);
                }

                if (preg_match('/^(.*) — v(\S+)$/u', $date, $hm)) {
                    $date = trim($hm[1]);
                    $version = trim($hm[2]);
                }

                $release = ['date' => $date, 'version' => $version, 'build' => $build, 'categories' => []];
                $categoryIndex = null;
                $itemIndex = null;

                continue;
            }

            if ($release === null) {
                continue;
            }

            if (preg_match('/^### (.+)$/', $line, $m)) {
                $release['categories'][] = ['name' => $m[1], 'items' => [], 'notes' => []];
                $categoryIndex = array_key_last($release['categories']);
                $itemIndex = null;

                continue;
            }

            if (preg_match('/^- (.+)$/', $line, $m)) {
                if ($categoryIndex === null) {
                    $release['categories'][] = ['name' => null, 'items' => [], 'notes' => []];
                    $categoryIndex = array_key_last($release['categories']);
                }

                $release['categories'][$categoryIndex]['items'][] = ['text' => $this->formatInline($m[1]), 'paragraphs' => [], 'children' => []];
                $itemIndex = array_key_last($release['categories'][$categoryIndex]['items']);

                continue;
            }

            if ($itemIndex !== null && preg_match('/^ {2}- (.+)$/', $line, $m)) {
                $release['categories'][$categoryIndex]['items'][$itemIndex]['children'][] = $this->formatInline($m[1]);

                continue;
            }

            // A paragraph indented under the last bullet continues it.
            if ($itemIndex !== null && preg_match('/^ {2}(\S.*)$/', $line, $m)) {
                $release['categories'][$categoryIndex]['items'][$itemIndex]['paragraphs'][] = $this->formatInline($m[1]);

                continue;
            }

            // A paragraph of its own, flush left: the "**Fixed:**" notes that
            // close a section. It belongs to the section, not to the last bullet.
            if (trim($line) !== '' && preg_match('/^\S.*$/', $line)) {
                if ($categoryIndex === null) {
                    $release['categories'][] = ['name' => null, 'items' => [], 'notes' => []];
                    $categoryIndex = array_key_last($release['categories']);
                }

                $release['categories'][$categoryIndex]['notes'][] = $this->formatInline($line);
                $itemIndex = null;
            }
        }

        if ($release !== null) {
            $releases[] = $release;
        }

        return $releases;
    }

    private function formatInline(string $text): string
    {
        $escaped = e($text);
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;

        return preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
    }
}
