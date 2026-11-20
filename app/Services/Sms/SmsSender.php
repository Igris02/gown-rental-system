<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends text messages. The service is chosen by SMS_DRIVER in .env (see config/notifications.php).
 * A failed text message never stops a booking: the problem is written to the log and that is all.
 */
class SmsSender
{
    /**
     * Turns what a person typed into a Philippine mobile number such as 09171234567,
     * or null when it is not a mobile number we can text.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (preg_match('/^63(9\d{9})$/', $digits, $m)) {   // +63 917 123 4567
            return '0' . $m[1];
        }

        if (preg_match('/^0(9\d{9})$/', $digits, $m)) {    // 0917 123 4567
            return '0' . $m[1];
        }

        if (preg_match('/^(9\d{9})$/', $digits, $m)) {     // 917 123 4567
            return '0' . $m[1];
        }

        return null;
    }

    public function driver(): string
    {
        return (string) config('notifications.sms_driver', 'log');
    }

    /**
     * @return bool|null true = sent, false = failed, null = not attempted (no usable number or SMS is off)
     */
    public function send(?string $phone, string $message): ?bool
    {
        $driver = $this->driver();
        $number = self::normalizePhone($phone);

        if ($driver === 'none' || $number === null) {
            return null;
        }

        try {
            if ($driver === 'semaphore') {
                return $this->viaSemaphore($number, $message);
            }

            // Default: only write it to the log, so nothing is sent while testing
            Log::info("[SMS to {$number}] {$message}");

            return true;
        } catch (Throwable $e) {
            Log::warning("SMS to {$number} failed: " . $e->getMessage());

            return false;
        }
    }

    private function viaSemaphore(string $number, string $message): bool
    {
        $key = config('notifications.semaphore.key');

        if (! $key) {
            Log::warning('SMS not sent: SEMAPHORE_API_KEY is empty in .env.');

            return false;
        }

        $fields = ['apikey' => $key, 'number' => $number, 'message' => $message];

        if ($sender = config('notifications.semaphore.sender')) {
            $fields['sendername'] = $sender;
        }

        $response = Http::asForm()->timeout(8)->post('https://api.semaphore.co/api/v4/messages', $fields);

        if (! $response->successful()) {
            Log::warning("SMS to {$number} failed: Semaphore answered {$response->status()} " . substr((string) $response->body(), 0, 300));

            return false;
        }

        return true;
    }
}
