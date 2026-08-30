@extends('layouts.admin')

@section('title', 'Company & legal settings')

@section('content')
    <div class="admin-list-page">
        <header class="admin-list-hero">
            <div class="admin-list-hero-row">
                <div>
                    <p class="admin-list-kicker"><a href="{{ route('admin.settings.index') }}">Settings</a></p>
                    <h2 class="admin-list-title">Company &amp; legal</h2>
                    <p class="admin-list-lede">
                        Used to fill in the placeholders on the CGV, mentions légales, privacy policy and
                        withdrawal pages. Anything left blank still shows as a bracketed placeholder on those pages.
                    </p>
                </div>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.settings.company.update') }}" class="admin-form-card admin-form-card--solo">
            @csrf
            @method('PUT')

            <h3 class="admin-panel-title">Company identity</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="company_name">Company name (raison sociale)</label>
                    <input type="text" id="company_name" name="company_name" class="form-control" value="{{ old('company_name', $setting->company_name) }}" maxlength="160">
                    @error('company_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="legal_form">Legal form</label>
                    <input type="text" id="legal_form" name="legal_form" class="form-control" value="{{ old('legal_form', $setting->legal_form) }}" maxlength="80" placeholder="e.g. SASU, entreprise individuelle">
                    @error('legal_form') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="share_capital">Share capital</label>
                    <input type="text" id="share_capital" name="share_capital" class="form-control" value="{{ old('share_capital', $setting->share_capital) }}" maxlength="80" placeholder="e.g. 1 000 €">
                    <p class="form-hint">Leave blank if you're an auto-entrepreneur / entreprise individuelle — no share capital applies.</p>
                    @error('share_capital') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="siret">SIRET</label>
                    <input type="text" id="siret" name="siret" class="form-control" value="{{ old('siret', $setting->siret) }}" maxlength="20">
                    @error('siret') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="vat_number">VAT number</label>
                    <input
                        type="text"
                        id="vat_number"
                        name="vat_number"
                        class="form-control"
                        value="{{ old('vat_number', $setting->vat_number) }}"
                        maxlength="30"
                        @disabled(old('vat_exempt', $setting->vat_exempt))
                    >
                    <label class="form-check">
                        <input
                            type="checkbox"
                            name="vat_exempt"
                            value="1"
                            id="vat_exempt"
                            @checked(old('vat_exempt', $setting->vat_exempt))
                        >
                        TVA non applicable, art. 293 B du CGI (franchise en base de TVA / auto-entrepreneur)
                    </label>
                    @error('vat_number') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="publication_director">Publication director</label>
                    <input type="text" id="publication_director" name="publication_director" class="form-control" value="{{ old('publication_director', $setting->publication_director) }}" maxlength="160">
                    @error('publication_director') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="address">Registered address</label>
                <input type="text" id="address" name="address" class="form-control" value="{{ old('address', $setting->address) }}" maxlength="255">
                @error('address') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <h3 class="admin-panel-title">Contact</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="contact_email">Contact email</label>
                    <input type="email" id="contact_email" name="contact_email" class="form-control" value="{{ old('contact_email', $setting->contact_email) }}" maxlength="160">
                    @error('contact_email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone', $setting->phone) }}" maxlength="30">
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="return_address">Return address (right of withdrawal)</label>
                <input type="text" id="return_address" name="return_address" class="form-control" value="{{ old('return_address', $setting->return_address) }}" maxlength="255">
                <p class="form-hint">Where customers should send returned products.</p>
                @error('return_address') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <h3 class="admin-panel-title">Consumer mediation</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="mediator_name">Mediator name</label>
                    <input type="text" id="mediator_name" name="mediator_name" class="form-control" value="{{ old('mediator_name', $setting->mediator_name) }}" maxlength="160">
                    <p class="form-hint">The consumer mediation scheme the shop joined (e.g. CM2C). Named in the CGV once filled.</p>
                    @error('mediator_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="mediator_url">Mediator website</label>
                    <input type="url" id="mediator_url" name="mediator_url" class="form-control" value="{{ old('mediator_url', $setting->mediator_url) }}" maxlength="255">
                    @error('mediator_url') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <h3 class="admin-panel-title">Hosting</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="host_name">Host name</label>
                    <input type="text" id="host_name" name="host_name" class="form-control" value="{{ old('host_name', $setting->host_name) }}" maxlength="160">
                    @error('host_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="host_phone">Host phone</label>
                    <input type="text" id="host_phone" name="host_phone" class="form-control" value="{{ old('host_phone', $setting->host_phone) }}" maxlength="30">
                    @error('host_phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="host_address">Host address</label>
                <input type="text" id="host_address" name="host_address" class="form-control" value="{{ old('host_address', $setting->host_address) }}" maxlength="255">
                @error('host_address') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection
