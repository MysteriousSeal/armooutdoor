@extends('layouts.admin')

@section('title', $category->exists ? 'Edit category' : 'Add category')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">{{ $category->exists ? 'Edit category' : 'Add category' }}</h2>
                    <p class="admin-list-lede">Name, description, and an optional parent category.</p>
                </div>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Back to categories</a>
            </div>
        </header>

        <form
            method="POST"
            action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
            class="admin-form-card admin-form-card--solo"
        >
            @csrf
            @if ($category->exists)
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $category->name['fr'] ?? '') }}" required maxlength="120">
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4" required maxlength="2000">{{ old('description', $category->description['fr'] ?? '') }}</textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="parent_id">Parent category</label>
                    <select id="parent_id" name="parent_id" class="form-control">
                        <option value="">None — top-level category</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $parent->id)>
                                {{ $parent->name['fr'] ?? $parent->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">Leave as "None" for a top-level category. Subcategories can't have their own subcategories.</p>
                    @error('parent_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="sort_order">Sort order</label>
                    <input type="number" id="sort_order" name="sort_order" class="form-control" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" max="9999">
                </div>
            </div>

            <div class="form-group">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" maxlength="80" placeholder="generated-from-name">
                <p class="form-hint">Leave blank to generate from the name.</p>
                @error('slug') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $category->exists ? 'Save changes' : 'Create category' }}</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

