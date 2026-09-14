{{-- Gaps that still block Download label. Named by Product::missingLabelRequirements. --}}
@if ($missing !== [])
    <ul class="label-requirements-missing">
        @foreach ($missing as $requirement)
            <li class="gtin-flag is-missing">Missing {{ $requirement }}</li>
        @endforeach
    </ul>
@endif
