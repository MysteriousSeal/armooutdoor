@php
    $bagErrors = $errors->{$bag};
    $val = fn (string $field) => $bagErrors->any() ? old($field, $snapshot[$field] ?? '') : ($snapshot[$field] ?? '');
@endphp

<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}_first_name">First name</label>
        <input type="text" id="{{ $prefix }}_first_name" name="first_name" class="form-control" value="{{ $val('first_name') }}" required maxlength="80">
        @if ($bagErrors->has('first_name')) <p class="form-error">{{ $bagErrors->first('first_name') }}</p> @endif
    </div>
    <div class="form-group">
        <label for="{{ $prefix }}_last_name">Last name</label>
        <input type="text" id="{{ $prefix }}_last_name" name="last_name" class="form-control" value="{{ $val('last_name') }}" required maxlength="80">
        @if ($bagErrors->has('last_name')) <p class="form-error">{{ $bagErrors->first('last_name') }}</p> @endif
    </div>
</div>

<div class="form-group">
    <label for="{{ $prefix }}_line1">Address</label>
    <input type="text" id="{{ $prefix }}_line1" name="line1" class="form-control" value="{{ $val('line1') }}" required maxlength="120">
    @if ($bagErrors->has('line1')) <p class="form-error">{{ $bagErrors->first('line1') }}</p> @endif
</div>

<div class="form-group">
    <label for="{{ $prefix }}_line2">Line 2</label>
    <input type="text" id="{{ $prefix }}_line2" name="line2" class="form-control" value="{{ $val('line2') }}" maxlength="120">
    @if ($bagErrors->has('line2')) <p class="form-error">{{ $bagErrors->first('line2') }}</p> @endif
</div>

<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}_postal_code">Postal code</label>
        <input type="text" id="{{ $prefix }}_postal_code" name="postal_code" class="form-control" value="{{ $val('postal_code') }}" required maxlength="12">
        @if ($bagErrors->has('postal_code')) <p class="form-error">{{ $bagErrors->first('postal_code') }}</p> @endif
    </div>
    <div class="form-group">
        <label for="{{ $prefix }}_city">City</label>
        <input type="text" id="{{ $prefix }}_city" name="city" class="form-control" value="{{ $val('city') }}" required maxlength="80">
        @if ($bagErrors->has('city')) <p class="form-error">{{ $bagErrors->first('city') }}</p> @endif
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}_country">Country</label>
        <select id="{{ $prefix }}_country" name="country" class="form-control" required>
            @foreach (config('shop.countries') as $country)
                <option value="{{ $country }}" @selected($val('country') === $country)>{{ __('store.country_'.$country) }}</option>
            @endforeach
        </select>
        @if ($bagErrors->has('country')) <p class="form-error">{{ $bagErrors->first('country') }}</p> @endif
    </div>
    <div class="form-group">
        <label for="{{ $prefix }}_phone">Phone</label>
        <input type="text" id="{{ $prefix }}_phone" name="phone" class="form-control" value="{{ $val('phone') }}" required maxlength="30">
        @if ($bagErrors->has('phone')) <p class="form-error">{{ $bagErrors->first('phone') }}</p> @endif
    </div>
</div>
