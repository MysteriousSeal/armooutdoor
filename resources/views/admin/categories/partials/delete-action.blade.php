@if ($blocked)
    <span class="btn btn-sm btn-secondary is-disabled" aria-disabled="true" title="{{ $blockedReason }} — remove those first">Remove</span>
@else
    <button type="button" class="btn btn-sm btn-secondary" data-modal-open="category-delete-{{ $category->id }}">Remove</button>
    <dialog id="category-delete-{{ $category->id }}" class="modal" aria-labelledby="category-delete-{{ $category->id }}-title">
        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
            @csrf
            @method('DELETE')
            <p class="modal-kicker">{{ $category->name['fr'] ?? $category->localizedName() }}</p>
            <h3 class="modal-title" id="category-delete-{{ $category->id }}-title">Remove this category?</h3>
            <p class="modal-body">This can't be undone.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Remove category</button>
            </div>
        </form>
    </dialog>
@endif
