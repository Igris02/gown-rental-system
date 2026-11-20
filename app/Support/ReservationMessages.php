<?php

namespace App\Support;

/**
 * The words of every email and text message, in one place.
 *
 * It only works with plain values, so it is easy to read, change, and test.
 * Text messages use plain English letters and numbers only, because one special
 * character (such as the peso sign) makes a text message cost more per part.
 */
class ReservationMessages
{
    public const EVENTS = [
        'received',        // an online booking arrived and waits for the shop
        'walk_in',         // staff booked it at the shop
        'confirmed',
        'rejected',
        'cancelled',
        'pickup_reminder',
        'return_reminder',
    ];

    /**
     * @param array{shop:string, name:string, reference:string, gown:string, code:string,
     *              pickup:string, use:string, return:string, pickup_short:string, return_short:string,
     *              price:string, price_sms:string, shop_phone:?string} $d
     * @return array{subject:string, heading:string, lines:string[], sms:string}
     */
    public static function build(string $event, array $d): array
    {
        $gown = "{$d['gown']} ({$d['code']})";
        $dates = [
            "Day 1, pick up: {$d['pickup']}",
            "Day 2, use: {$d['use']}",
            "Day 3, return: {$d['return']}",
        ];
        $ref = $d['reference'];
        $shop = $d['shop'];
        $call = $d['shop_phone'] ? " Questions? Call {$d['shop_phone']}." : '';

        switch ($event) {
            case 'received':
                return [
                    'subject' => "We received your reservation {$ref}",
                    'heading' => 'We received your reservation',
                    'lines' => array_merge(
                        ["Hi {$d['name']}, thank you for choosing {$shop}.", "Gown: {$gown}"],
                        $dates,
                        ["Rental price: {$d['price']}", 'The shop will review your reservation and send you another message once it is confirmed. Your dates are held for you in the meantime.']
                    ),
                    'sms' => "{$shop}: We got your reservation {$ref} for {$d['gown']}, pick up {$d['pickup_short']}. We will message you once it is confirmed.",
                ];

            case 'walk_in':
                return [
                    'subject' => "Your reservation {$ref} is confirmed",
                    'heading' => 'Your reservation is confirmed',
                    'lines' => array_merge(
                        ["Hi {$d['name']}, thank you for visiting {$shop}.", "Gown: {$gown}"],
                        $dates,
                        ["Rental price: {$d['price']}", 'Please keep this message and quote the reference when you pick up the gown.']
                    ),
                    'sms' => "{$shop}: Reservation {$ref} confirmed. {$d['gown']}, pick up {$d['pickup_short']}, return {$d['return_short']}. Price {$d['price_sms']}.",
                ];

            case 'confirmed':
                return [
                    'subject' => "Your reservation {$ref} is confirmed",
                    'heading' => 'Your reservation is confirmed',
                    'lines' => array_merge(
                        ["Hi {$d['name']}, good news. {$shop} confirmed your reservation.", "Gown: {$gown}"],
                        $dates,
                        ["Rental price: {$d['price']}", 'Please quote the reference when you pick up the gown.']
                    ),
                    'sms' => "{$shop}: Reservation {$ref} is confirmed. {$d['gown']}, pick up {$d['pickup_short']}, return {$d['return_short']}.",
                ];

            case 'rejected':
                return [
                    'subject' => "About your reservation {$ref}",
                    'heading' => 'We could not accept your reservation',
                    'lines' => [
                        "Hi {$d['name']}, sorry, {$shop} could not accept reservation {$ref} for {$gown} on {$d['pickup']}.",
                        'The dates are free again. You are welcome to choose another date or another gown.',
                    ],
                    'sms' => "{$shop}: Sorry, we could not accept reservation {$ref} for {$d['gown']}. You may pick another date or gown.{$call}",
                ];

            case 'cancelled':
                return [
                    'subject' => "Your reservation {$ref} was cancelled",
                    'heading' => 'Your reservation was cancelled',
                    'lines' => [
                        "Hi {$d['name']}, reservation {$ref} for {$gown} ({$d['pickup']} to {$d['return']}) was cancelled.",
                        'The dates were released. If this is a mistake, please contact the shop.',
                    ],
                    'sms' => "{$shop}: Reservation {$ref} for {$d['gown']} on {$d['pickup_short']} was cancelled.{$call}",
                ];

            case 'pickup_reminder':
                return [
                    'subject' => "Reminder: pick up your gown tomorrow ({$ref})",
                    'heading' => 'Your pick-up day is tomorrow',
                    'lines' => array_merge(
                        ["Hi {$d['name']}, this is a reminder from {$shop}.", "Gown: {$gown}"],
                        $dates,
                        ['Please quote your reference when you arrive.']
                    ),
                    'sms' => "{$shop}: Reminder, pick up {$d['gown']} tomorrow ({$d['pickup_short']}). Ref {$ref}. Return it on {$d['return_short']}.",
                ];

            case 'return_reminder':
                return [
                    'subject' => "Reminder: return your gown tomorrow ({$ref})",
                    'heading' => 'Your return day is tomorrow',
                    'lines' => [
                        "Hi {$d['name']}, this is a reminder from {$shop}.",
                        "Please return {$gown} tomorrow, {$d['return']}.",
                        'Thank you for renting with us.',
                    ],
                    'sms' => "{$shop}: Reminder, please return {$d['gown']} tomorrow ({$d['return_short']}). Ref {$ref}. Thank you!",
                ];
        }

        throw new \InvalidArgumentException("Unknown reservation event: {$event}");
    }
}
