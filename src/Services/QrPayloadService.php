<?php

declare(strict_types=1);

namespace Clinically\Companion\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\PlainTextRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Clinically\Companion\Models\CompanionAgent;

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

        if (! class_exists(Writer::class)) {
            return '';
        }

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString(
            $payload,
            ecLevel: ErrorCorrectionLevel::M(),
        );
    }

    /**
     * Generate an ASCII representation of a QR code for terminal output.
     */
    public function generateQrAscii(CompanionAgent $agent, string $plainToken): string
    {
        $payload = json_encode($this->buildPayload($agent, $plainToken));

        if (! class_exists(Writer::class)) {
            return "QR code generation requires bacon/bacon-qr-code.\nPayload: {$payload}";
        }

        try {
            return (new Writer(new PlainTextRenderer))->writeString(
                $payload,
                ecLevel: ErrorCorrectionLevel::M(),
            );
        } catch (\Throwable) {
            return "QR payload: {$payload}";
        }
    }
}
