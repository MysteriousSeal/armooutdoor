{{-- A variant's three photo slots. The first is the main photo, the one the
     variant chip, the cart and the orders show. Saving packs the slots to the
     front, so a photo left alone after an emptied slot moves up.

     $index: the row index in the form ('__INDEX__' in the template).
     $product, $variantModel: the saved product and variant, or null. --}}
@php($photos = $variantModel?->photos() ?? [])
<div class="variant-photo-slots">
    @foreach (\App\Models\ProductVariant::PHOTO_COLUMNS as $slot => $column)
        @php($photo = $photos[$slot] ?? null)
        <div class="variant-photo-slot">
            <span class="variant-photo-slot-label">{{ $slot === 0 ? 'Main photo' : 'Photo '.($slot + 1) }}</span>
            <span class="variant-card-preview">
                @if ($photo)
                    <img src="{{ asset('images/'.$photo) }}" alt="">
                @endif
            </span>
            @if ($photo)
                @include('admin.products.partials.photo-download', [
                    'href' => route('admin.products.photos.variant', ['product' => $product, 'variant' => $variantModel, 'position' => $slot + 1]),
                    'filename' => $variantModel->setRelation('product', $product)->photoDownloadName($slot + 1),
                ])
                <label class="form-check">
                    <input type="checkbox" name="variants[{{ $index }}][remove_images][{{ $slot }}]" value="1">
                    Remove
                </label>
            @endif
            <label class="btn btn-sm btn-secondary variant-card-upload">
                {{ $photo ? 'Replace' : 'Add' }}
                <input type="file" name="variant_images[{{ $index }}][{{ $slot }}]" accept="image/jpeg,image/png,image/gif,image/webp">
            </label>
        </div>
    @endforeach
</div>
