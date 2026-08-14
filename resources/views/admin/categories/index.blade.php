@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker">Catalog</p>
                    <h2 class="admin-list-title">Categories</h2>
                    <p class="admin-list-lede">Top-level categories and their subcategories. Subcategories can't have their own subcategories.</p>
                </div>
                <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Add category</a>
            </div>
        </header>

        @if ($categories->isEmpty())
            <div class="empty-state">
                <p>No categories yet.</p>
                <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Add category</a>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Products</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.categories.edit', $category) }}">
                                        {{ $category->name['fr'] ?? $category->localizedName() }}
                                    </a>
                                </td>
                                <td>{{ $category->slug }}</td>
                                <td>{{ $category->products_count }}</td>
                                <td>
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-secondary">Edit</a>
                                </td>
                            </tr>
                            @foreach ($category->children as $child)
                                <tr>
                                    <td class="admin-table-indent">— {{ $child->name['fr'] ?? $child->localizedName() }}</td>
                                    <td>{{ $child->slug }}</td>
                                    <td>{{ $child->products_count }}</td>
                                    <td>
                                        <a href="{{ route('admin.categories.edit', $child) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
