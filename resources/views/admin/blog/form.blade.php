@extends('layouts.admin')

@section('title', ($post->exists ? 'Edit post' : 'New post').' — Admin')

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/vendor/quill.snow.css') }}">
@endpush

@section('content')
    <div class="admin-list-page admin-blog-compose">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.blog.index') }}">Blog</a></p>
                    <h2 class="admin-list-title">{{ $post->exists ? $post->localizedTitle() : 'New post' }}</h2>
                    @if ($post->exists)
                        <p class="admin-list-lede">
                            <code>/blog/{{ $post->slug }}</code>
                            @if ($post->isVisible())
                                · <a href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener noreferrer">View</a>
                            @endif
                        </p>
                    @else
                        <p class="admin-list-lede">A guide, a shop note, a product trial or a legal piece. Drafts stay private until you publish.</p>
                    @endif
                </div>
                <div class="admin-list-hero-actions">
                    <a href="{{ route('admin.blog.index') }}" class="btn btn-secondary">Back to blog</a>
                    @if ($post->exists)
                        <button type="button" class="btn btn-secondary" data-modal-open="delete-post-modal">Delete</button>
                    @endif
                </div>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}"
            enctype="multipart/form-data"
            class="admin-form"
        >
            @csrf
            @if ($post->exists)
                @method('PUT')
            @endif

            <div class="admin-form-grid">
                <div class="admin-form-main">
                    <section class="admin-card admin-card--compose">
                        <div class="form-group blog-title-group">
                            <label for="title" class="sr-only">Title</label>
                            <input type="text" id="title" name="title" class="form-control blog-title-input" maxlength="180" required
                                   placeholder="Post title"
                                   value="{{ old('title', $post->exists ? $post->localizedTitle() : '') }}">
                            @error('title') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label for="excerpt">Excerpt</label>
                            <textarea id="excerpt" name="excerpt" class="form-control blog-excerpt-input" rows="2" maxlength="300" placeholder="One or two sentences for the listing card…">{{ old('excerpt', $post->exists ? $post->localizedExcerpt() : '') }}</textarea>
                            <p class="form-hint">Shown on the card, and used as the meta description when no SEO description is set.</p>
                            @error('excerpt') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group description-editor-group" data-image-upload-url="{{ route('admin.blog.images') }}">
                            <label for="body">Body</label>
                            <div class="description-editor" aria-label="Post editor"></div>
                            <textarea id="body" name="body" class="description-editor-source" hidden placeholder="Write the post…">{{ old('body', $post->exists ? $post->localizedBody() : '') }}</textarea>
                            <p class="form-hint">Rich text. Images are uploaded to this site — pasted external images are dropped.</p>
                            @error('body') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    <section class="admin-card">
                        <h3 class="admin-card-title">Produits mentionnés</h3>
                        <p class="form-hint blog-card-lede">Shown as cards at the end of the post, and the product page links back here.</p>
                        <div
                            class="blog-product-picker"
                            data-products="{{ json_encode($productOptions, JSON_UNESCAPED_UNICODE) }}"
                        >
                            <input type="text" class="form-control blog-product-search" placeholder="Search a product by name or SKU…" autocomplete="off">
                            <ul class="blog-product-results" hidden></ul>
                            <ul class="blog-product-selected">@foreach ($selectedProducts as $product)
                                <li data-id="{{ $product->id }}">
                                    <span class="blog-product-copy">
                                        <span class="blog-product-name">{{ $product->localizedName() }}</span>
                                        @if ($product->sku)
                                            <span class="blog-product-sku">{{ $product->sku }}</span>
                                        @endif
                                    </span>
                                    <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
                                    <button type="button" class="blog-product-remove" data-remove aria-label="Remove">&times;</button>
                                </li>
                            @endforeach</ul>
                        </div>
                    </section>

                    <section class="admin-card">
                        <h3 class="admin-card-title">SEO</h3>
                        <div class="blog-seo-grid">
                            <div class="form-group">
                                <label for="meta_title">Meta title</label>
                                <input type="text" id="meta_title" name="meta_title" class="form-control" maxlength="180"
                                       placeholder="Falls back to the title"
                                       value="{{ old('meta_title', $post->meta_title) }}">
                                <p class="form-hint">Falls back to the title.</p>
                            </div>
                            <div class="form-group">
                                <label for="meta_description">Meta description</label>
                                <textarea id="meta_description" name="meta_description" class="form-control" rows="2" maxlength="300" placeholder="Falls back to the excerpt">{{ old('meta_description', $post->meta_description) }}</textarea>
                                <p class="form-hint">Falls back to the excerpt.</p>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="admin-form-side">
                    <section class="admin-card">
                        <h3 class="admin-card-title">Publication</h3>
                        <div class="form-group">
                            <span class="blog-field-label" id="blog-status-label">Status</span>
                            <div class="blog-status-toggle" role="group" aria-labelledby="blog-status-label">
                                <label class="blog-status-option">
                                    <input type="radio" name="status" value="draft" @checked(old('status', $post->status) === 'draft')>
                                    <span>Draft</span>
                                </label>
                                <label class="blog-status-option">
                                    <input type="radio" name="status" value="published" @checked(old('status', $post->status) === 'published')>
                                    <span>Published</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="published_at">Publish date</label>
                            <input type="datetime-local" id="published_at" name="published_at" class="form-control"
                                   value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
                            <p class="form-hint">A future date keeps the post private until then.</p>
                            @error('published_at') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="form-group">
                            <label for="blog_category_id">Category</label>
                            <select id="blog_category_id" name="blog_category_id" class="form-control" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('blog_category_id', $post->blog_category_id) == $category->id)>
                                        {{ $category->localizedName() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('blog_category_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    <section class="admin-card">
                        <h3 class="admin-card-title">Cover image</h3>
                        <label class="blog-cover-slot{{ $post->image ? ' has-image' : '' }}">
                            @if ($post->image)
                                <img src="{{ $post->cardUrl() }}" alt="" class="blog-cover-preview" width="800" height="450">
                            @endif
                            <span class="blog-cover-empty">
                                <span class="blog-cover-empty-kicker">Cover</span>
                                <span class="blog-cover-empty-title">{{ $post->image ? 'Replace the cover' : 'Add a cover' }}</span>
                                <span class="blog-cover-empty-hint">Landscape · 1600×900 · JPEG, PNG or WebP</span>
                            </span>
                            <input type="file" name="image_file" id="image_file" accept="image/*" aria-label="Cover image">
                        </label>
                        @if ($post->image)
                            <label class="form-check blog-cover-remove">
                                <input type="checkbox" name="remove_image" value="1"> Remove this image
                            </label>
                        @endif
                        <p class="form-hint">Resized to 1600×900, with an 800×450 card thumbnail.</p>

                        <div class="form-group blog-cover-credit">
                            <label for="image_credit">Credit <span class="form-optional">optional</span></label>
                            {{-- Le préfixe est montré dans le champ plutôt que
                                 décrit en dessous : on voit ce qui sortira. --}}
                            <div class="input-with-prefix">
                                <span class="input-prefix" aria-hidden="true">{{ __('store.blog_image_credit_prefix') }}</span>
                                <input
                                    type="text"
                                    id="image_credit"
                                    name="image_credit"
                                    class="form-control"
                                    maxlength="180"
                                    placeholder="Umarex"
                                    value="{{ old('image_credit', $post->image_credit) }}"
                                >
                            </div>
                            <p class="form-hint">Just the name. « {{ __('store.blog_image_credit_prefix') }} » is added on the page. Left empty, nothing appears.</p>
                            @error('image_credit') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        @error('image_file') <p class="form-error">{{ $message }}</p> @enderror
                    </section>

                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-primary btn-block">{{ $post->exists ? 'Save post' : 'Create post' }}</button>
                        <a href="{{ route('admin.blog.index') }}" class="btn btn-secondary btn-block">Cancel</a>
                    </div>
                </aside>
            </div>
        </form>

        @if ($post->exists)
            <dialog id="delete-post-modal" class="modal" aria-labelledby="delete-post-title">
                <form method="POST" action="{{ route('admin.blog.destroy', $post) }}">
                    @csrf
                    @method('DELETE')
                    <h3 class="modal-title" id="delete-post-title">Delete this post?</h3>
                    <p class="modal-body">The post and its cover image are removed for good. Images used inside the text are left on disk.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary">Delete</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/vendor/quill.js') }}"></script>
    <script src="{{ versioned_asset('js/admin-description-editor.js') }}" defer></script>
    <script src="{{ versioned_asset('js/admin-blog-form.js') }}" defer></script>
@endpush
