<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Notifications\AdminBlogCommentReceived;
use App\Support\AdminMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Comments under an article: anyone may write, everything publishes at
 * once, and the shop hears about each one by mail - the inbox is the
 * moderation queue. The shop's own answers and the pruning happen on
 * the article page itself, behind the admin gate.
 */
class BlogCommentController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $post = BlogPost::query()->visible()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'author_name' => [$request->user() === null ? 'required' : 'nullable', 'string', 'max:40'],
            // The honeypot: a field no human sees, so a filled one is a bot.
            'website' => ['prohibited'],
        ], [
            'author_name.required' => 'Choisissez un pseudonyme pour signer votre commentaire.',
            'body.required' => 'Écrivez votre commentaire avant de l\'envoyer.',
        ]);

        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id,
            'user_id' => $request->user()?->id,
            'author_name' => $request->user() === null ? trim($validated['author_name']) : null,
            'body' => trim($validated['body']),
        ]);

        AdminMail::notify(
            new AdminBlogCommentReceived($comment),
            'Could not email the blog comment notice.',
            ['comment_id' => $comment->id],
        );

        return redirect()->to(route('blog.show', $post->slug).'#commentaires')
            ->with('comment_status', 'Merci, votre commentaire est en ligne.');
    }

    /** The shop answers under its own name, one level deep. */
    public function reply(Request $request, BlogComment $comment): RedirectResponse
    {
        abort_if($comment->parent_id !== null, 422);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        BlogComment::query()->create([
            'blog_post_id' => $comment->blog_post_id,
            'user_id' => $request->user()->id,
            'parent_id' => $comment->id,
            'is_admin' => true,
            'body' => trim($validated['body']),
        ]);

        return redirect()->to(route('blog.show', $comment->post->slug).'#commentaires');
    }

    public function destroy(BlogComment $comment): RedirectResponse
    {
        $slug = $comment->post->slug;
        $comment->delete();

        return redirect()->to(route('blog.show', $slug).'#commentaires');
    }
}
