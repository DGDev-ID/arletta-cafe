<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class TelegramService
{

    public function sendMessage(
        string $message,
        ?string $parseMode = null,
        string $botToken = "9276029966:AAEhQONIN7lQePKkJVOCApD0ozX59ntiOb0",
        string|int $chatId = "-5502995799"
    ): array {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $payload = [
            'chat_id' => $chatId,
            'text'    => $message,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        $response = Http::timeout(15)
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Telegram API error: ' . $response->body()
            );
        }

        $result = $response->json();

        if (! ($result['ok'] ?? false)) {
            throw new RuntimeException(
                'Telegram gagal mengirim pesan.'
            );
        }

        return $result;
    }
}