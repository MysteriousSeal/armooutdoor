{{-- One photo as a JPEG, converted on the way out: the shop stores WebP,
     which marketplace forms will not take. `download` names the file on the
     browser's side too, as on the Vinted page.

     $path (optional): the photo's path under public/images, to print its
     size above the link, read from the file itself. --}}
@php
    $photoSize = isset($path) && ! str_starts_with((string) $path, 'http') && is_file(public_path('images/'.$path))
        ? @getimagesize(public_path('images/'.$path))
        : false;
@endphp
@if ($photoSize)
    <span class="photo-size" title="Size of the stored photo, in pixels">{{ $photoSize[0] }} × {{ $photoSize[1] }} px</span>
@endif
<a
    href="{{ $href }}"
    download="{{ $filename }}"
    class="photo-download"
    title="Download {{ $filename }}, full size"
>
    <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true">
        <path d="M12 4v11m0 0-4-4m4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    JPG
</a>
