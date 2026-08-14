<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(): View
    {
        return view('account.addresses.index', [
            'addresses' => request()->user()->addresses()->get(),
        ]);
    }

    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $makeDefault = $user->addresses()->doesntExist() || $request->boolean('is_default');

        if ($makeDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create([
            ...$request->safe()->except('is_default'),
            'is_default' => $makeDefault,
        ]);

        return redirect(localized_route('account.addresses.index'))
            ->with('status', __('store.address_saved'));
    }

    public function edit(Address $address): View
    {
        $this->authorizeAddress($address);

        return view('account.addresses.edit', compact('address'));
    }

    public function update(StoreAddressRequest $request, Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        $makeDefault = $request->boolean('is_default') || $address->is_default;

        if ($makeDefault) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        $address->update([
            ...$request->safe()->except('is_default'),
            'is_default' => $makeDefault,
        ]);

        return redirect(localized_route('account.addresses.index'))
            ->with('status', __('store.address_updated'));
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        $wasDefault = $address->is_default;
        $user = $address->user;
        $address->delete();

        if ($wasDefault) {
            $user->addresses()->first()?->makeDefault();
        }

        return redirect(localized_route('account.addresses.index'))
            ->with('status', __('store.address_deleted'));
    }

    public function makeDefault(Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);
        $address->makeDefault();

        return redirect(localized_route('account.addresses.index'))
            ->with('status', __('store.address_default_set'));
    }

    private function authorizeAddress(Address $address): void
    {
        abort_unless($address->user_id === request()->user()->id, 404);
    }
}
