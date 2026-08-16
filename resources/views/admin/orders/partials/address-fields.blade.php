@php
    $fieldPrefix = $fieldPrefix ?? '';
    $phoneOptional = $phoneOptional ?? false;
    $bagErrors = $errors->{$bag};
    $field = fn (string $name) => $fieldPrefix.$name;
    $val = fn (string $name) => $bagErrors->any()
        ? old($field($name), $snapshot[$name] ?? '')
        : ($snapshot[$name] ?? '');
@endphp

<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}_first_name">First name</label>
        <input type="text" id="{{ $prefix }}_first_name" name="{{ $field('first_name') }}" class="form-control" value="{{ $val('first_name') }}" required maxlength="80">
        @if ($bagErrors->has($field('first_name'))) <p class="form-error">{{ $bagErrors->first($field('first_name')) }}</p> @endif
    </div>
    <div class="form-group">
        <label for="{{ $prefix }}_last_name">Last name</label>
        <input type="text" id="{{ $prefix }}_last_name" name="{{ $field('last_name') }}" class="form-control" value="{{ $val('last_name') }}" required maxlength="80">
        @if ($bagErrors->has($field('last_name'))) <p class="form-error">{{ $bagErrors->first($field('last_name')) }}</p> @endif
    </div>
</div>

<div class="form-group">
    <label for="{{ $prefix }}_line1">Address</label>
    <input type="text" id="{{ $prefix }}_line1" name="{{ $field('line1') }}" class="form-control" value="{{ $val('line1') }}" required maxlength="120">
    @if ($bagErrors->has($field('line1'))) <p class="form-error">{{ $bagErrors->first($field('line1')) }}</p> @endif
</div>

<div class="form-group">
    <label for="{{ $prefix }}_line2">Line 2</label>
    <input type="text" id="{{ $prefix }}_line2" name="{{ $field('line2') }}" class="form-control" value="{{ $val('line2') }}" maxlength="120">
    @if ($bagErrors->has($field('line2'))) <p class="form-error">{{ $bagErrors->first($field('line2')) }}</p> @endif
</div>

<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}_postal_code">Postal code</label>
        <input type="text" id="{{ $prefix }}_postal_code" name="{{ $field('postal_code') }}" class="form-control" value="{{ $val('postal_code') }}" required maxlength="12">
        @if ($bagErrors->has($field('postal_code'))) <p class="form-error">{{ $bagErrors->first($field('postal_code')) }}</p> @endif
    </div>
    <div class="form-group">
        <label for="{{ $prefix }}_city">City</label>
        <input type="text" id="{{ $prefix }}_city" name="{{ $field('city') }}" class="form-control" value="{{ $val('city') }}" required maxlength="80">
        @if ($bagErrors->has($field('city'))) <p class="form-error">{{ $bagErrors->first($field('city')) }}</p> @endif
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}_country">Country</label>
        <select id="{{ $prefix }}_country" name="{{ $field('country') }}" class="form-control" required>
            @foreach (config('shop.countries') as $country)
                <option value="{{ $country }}" @selected($val('country') === $country || ($val('country') === '' && $country === 'FR'))>{{ __('store.country_'.$country) }}</option>
            @endforeach
        </select>
        @if ($bagErrors->has($field('country'))) <p class="form-error">{{ $bagErrors->first($field('country')) }}</p> @endif
    </div>
    <div class="form-group">
        <label for="{{ $prefix }}_phone">Phone{{ $phoneOptional ? ' (optional)' : '' }}</label>
        <input type="text" id="{{ $prefix }}_phone" name="{{ $field('phone') }}" class="form-control" value="{{ $val('phone') }}" @unless($phoneOptional) required @endunless maxlength="30">
        @if ($bagErrors->has($field('phone'))) <p class="form-error">{{ $bagErrors->first($field('phone')) }}</p> @endif
    </div>
</div>
