{{-- One attribute of the category: a closed list, or free text. The names and
     ids are what the form posts and what the fill script looks for. --}}
@php($value = $isCurrent ? ($answers[$field['code']] ?? '') : '')
<div class="form-group octopia-field">
    <label for="octopia-{{ $template->id }}-{{ $field['code'] }}">
        {{ $field['label'] }}@if ($field['required'])<span class="octopia-required" title="Required by Octopia">*</span>@endif
    </label>

    @if (! empty($field['options']))
        <select
            name="values[{{ $field['code'] }}]"
            id="octopia-{{ $template->id }}-{{ $field['code'] }}"
            class="form-control"
            @unless($isCurrent) disabled @endunless
        >
            <option value="">—</option>
            @foreach ($field['options'] as $option)
                <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
            @endforeach
        </select>
    @else
        <input
            type="text"
            name="values[{{ $field['code'] }}]"
            id="octopia-{{ $template->id }}-{{ $field['code'] }}"
            class="form-control"
            value="{{ $value }}"
            @unless($isCurrent) disabled @endunless
        >
    @endif

    @if ($field['constraint'])
        <p class="form-hint">{{ $field['constraint'] }}</p>
    @endif

    @if ($activeVariants->isNotEmpty())
        <label class="form-check octopia-per-variant">
            <input
                type="checkbox"
                name="per_variant[]"
                value="{{ $field['code'] }}"
                @checked($isCurrent && in_array($field['code'], $perVariant, true))
                @unless($isCurrent) disabled @endunless
            >
            Answered per variant
        </label>
    @endif
</div>
