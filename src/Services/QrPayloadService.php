<?php

declare(strict_types=1);

namespace Clinically\Companion\Services;

use Clinically\Companion\Models\CompanionAgent;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class QrPayloadService
{
    /**
     * Build the JSON payload for a QR pairing code.
     *
     * @return array<string, mixed>
     */
    public function buildPayload(CompanionAgent $agent, string $plainToken): array
    {
        return [
            'v' => 1,
            'url' => config('app.url'),
            'path' => '/'.config('companion.path', 'companion').'/api',
            'token' => $plainToken,
            'name' => config('app.name').' — '.config('app.env'),
            'env' => config('app.env'),
            'expires_at' => $agent->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Generate a QR code SVG string for the pairing payload.
     */
    public function generateQrSvg(CompanionAgent $agent, string $plainToken): string
    {
        $payload = json_encode($this->buildPayload($agent, $plainToken));

        if (! class_exists(QrCode::class)) {
            return '';
        }

        return (string) QrCode::format('svg')
            ->size(300)
            ->errorCorrection('M')
            ->generate($payload);
    }

    /**
     * Generate an ASCII representation of a QR code for terminal output.
     */
    public function generateQrAscii(CompanionAgent $agent, string $plainToken): string
    {
        $payload = json_encode($this->buildPayload($agent, $plainToken));

        if (! class_exists(QrCode::class)) {
            return "QR code generation requires simplesoftwareio/simple-qrcode.\nPayload: {$payload}";
        }

        // Use the library to generate a string format if available
        try {
            return (string) QrCode::generate($payload);
        } catch (\Throwable) {
            return "QR payload: {$payload}";
        }
    }
}
