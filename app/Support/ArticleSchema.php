<?php

namespace App\Support;

use App\Models\BlogPost;
use Illuminate\Support\Str;

/**
 * An article, as the search engines read it.
 *
 * The blog carried a breadcrumb trail and nothing else: seven pieces of three
 * thousand words apiece, each written to be found, and none of them saying
 * when it was published, who published it, or what it is. They are the shop's
 * strongest pages for anything other than a product query, and they were
 * declaring less about themselves than a category listing does.
 */
class ArticleSchema
{
    /**
     * Google reads a headline up to about a hundred and ten characters and
     * ignores what is written past it.
     */
    private const MAX_HEADLINE = 110;

    /** @return array<string, mixed> */
    public static function for(BlogPost $post): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => Str::limit($post->localizedTitle(), self::MAX_HEADLINE, ''),
            'description' => $post->metaDescription(),
            'image' => $post->heroUrl(),
            'datePublished' => $post->published_at?->toAtomString(),
            // The date a reader should judge the advice by. It falls back to
            // publication rather than to nothing: an article that has never
            // been touched was last correct on the day it went up.
            'dateModified' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
            // The sources shown at the article's foot, said in schema too -
            // as works with a name, not bare URLs, so the label travels.
            'citation' => collect($post->sourcesList())->map(fn (array $source): array => [
                '@type' => 'CreativeWork',
                'name' => $source['label'],
                'url' => $source['url'],
            ])->all() ?: null,
            // No article here carries a byline, so the shop stands behind its
            // own writing rather than inventing a name to sign it.
            'author' => OrganizationSchema::reference(),
            'publisher' => OrganizationSchema::reference(),
            'mainEntityOfPage' => route('blog.show', $post->slug),
            'articleSection' => $post->category?->localizedName(),
            'inLanguage' => 'fr-FR',
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
