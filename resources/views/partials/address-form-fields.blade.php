@php
    /** @var \App\Models\Address|null $address */
    $address = $address ?? null;
@endphp

<div class="form-row">
    <div class="form-group">
        <label for="first_name">{{ __('store.first_name') }}</label>
        <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name', $address?->first_name) }}" required maxlength="80" autocomplete="given-name">
        @error('first_name') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div class="form-group">
        <label for="last_name">{{ __('store.last_name') }}</label>
        <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name', $address?->last_name) }}" required maxlength="80" autocomplete="family-name">
        @error('last_name') <p class="form-error">{{ $message }}</p> @enderror
    </div>
</div>

<div class="form-group">
    <label for="line1">{{ __('store.line1') }}</label>
    <input type="text" id="line1" name="line1" class="form-control" value="{{ old('line1', $address?->line1) }}" required maxlength="120" autocomplete="address-line1">
    @error('line1') <p class="form-error">{{ $message }}</p> @enderror
</div>

<div class="form-group">
    <label for="line2">{{ __('store.line2') }}</label>
    <input type="text" id="line2" name="line2" class="form-control" value="{{ old('line2', $address?->line2) }}" maxlength="120" autocomplete="address-line2">
</div>

<div class="form-row">
    <div class="form-group">
        <label for="postal_code">{{ __('store.postal_code') }}</label>
        <input type="text" id="postal_code" name="postal_code" class="form-control" value="{{ old('postal_code', $address?->postal_code) }}" required maxlength="12" autocomplete="postal-code">
        @error('postal_code') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div class="form-group">
        <label for="city">{{ __('store.city') }}</label>
        <input type="text" id="city" name="city" class="form-control" value="{{ old('city', $address?->city) }}" required maxlength="80" autocomplete="address-level2">
        @error('city') <p class="form-error">{{ $message }}</p> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label for="country">{{ __('store.country') }}</label>
        <select id="country" name="country" class="form-control" required autocomplete="country">
            @foreach (config('shop.customer_countries') as $country)
                <option value="{{ $country }}" @selected(old('country', $address?->country ?? 'FR') === $country)>{{ __('store.country_'.$country) }}</option>
            @endforeach
        </select>
        @error('country') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div class="form-group">
        <label for="phone">{{ __('store.phone') }}</label>
        <input type="tel" id="phone" name="phone" class="form-control" value="{{ old('phone', $address?->phone) }}" required maxlength="30" autocomplete="tel">
        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
    </div>
</div>

<div class="form-group">
    <label for="label">{{ __('store.address_label') }}</label>
    <input type="text" id="label" name="label" class="form-control" value="{{ old('label', $address?->label) }}" maxlength="40" placeholder="{{ __('store.address_label_placeholder') }}">
</div>

<div class="form-group">
    <label class="form-check">
        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address?->is_default ?? true))>
        {{ __('store.make_default') }}
    </label>
</div>
