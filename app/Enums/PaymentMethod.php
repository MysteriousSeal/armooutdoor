<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case PayPal = 'paypal';

    public function label(): string
    {
        return match ($this) {
            self::Card => __('store.payment_card'),
            self::PayPal => __('store.payment_paypal'),
        };
    }
}
