<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PayCools API client (v2 Checkout).
 *
 * Wire envelope: { "appId": <merchant>, "sign": <base64 RSA sig>, "param": <JSON string> }.
 * Signature target is the `param` JSON string byte-for-byte. Algorithm: SHA256withRSA,
 * signature base64-encoded.
 */
final class PayCoolsClient
{
    public function __construct(
        private readonly string $appId,
        private readonly string $privateKeyPem,
        private readonly string $publicKeyPem,
        private readonly string $baseUrl,
    ) {
    }

    public static function fromConfig(): self
    {
        $appId      = (string) config('services.paycools.app_id', '');
        $privateKey = self::normalizePem((string) config('services.paycools.private_key', ''));
        $publicKey  = self::normalizePem((string) config('services.paycools.public_key', ''));
        $baseUrl    = rtrim((string) config('services.paycools.base_url', ''), '/');

        if ($appId === '' || $privateKey === '' || $baseUrl === '') {
            throw new RuntimeException('PayCools credentials are not fully configured.');
        }

        return new self($appId, $privateKey, $publicKey, $baseUrl);
    }

    public function envelope(array $param): array
    {
        $param['timestamp'] = (string) (int) (microtime(true) * 1000);

        $paramJson = json_encode(
            $param,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if ($paramJson === false) {
            throw new RuntimeException('Failed to JSON-encode PayCools param.');
        }

        return [
            'appId' => $this->appId,
            'sign'  => $this->signParamJson($paramJson),
            'param' => $paramJson,
        ];
    }

    public function createOrder(array $param): array
    {
        return $this->post('/open-api/payment/checkout/generate', $param);
    }

    public function queryOrder(string $checkoutId): array
    {
        return $this->post('/open-api/payment/checkout/query', [
            'checkoutId' => $checkoutId,
        ]);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader = null): bool
    {
        if ($this->publicKeyPem === '') {
            return false;
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            return false;
        }

        $signature = $signatureHeader ?: ($decoded['sign'] ?? null);
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $paramJson = (string) ($decoded['param'] ?? '');
        if ($paramJson === '') {
            return false;
        }

        return $this->verifyRsa($paramJson, $signature);
    }

    public function decodeWebhookParam(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded) || ! isset($decoded['param'])) {
            return [];
        }

        $inner = json_decode((string) $decoded['param'], true);
        return is_array($inner) ? $inner : [];
    }

    private function signParamJson(string $paramJson): string
    {
        $pkey = openssl_pkey_get_private($this->privateKeyPem);
        if ($pkey === false) {
            throw new RuntimeException('Invalid PayCools merchant private key.');
        }

        $signature = '';
        $ok = openssl_sign($paramJson, $signature, $pkey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('PayCools signing failed.');
        }

        return base64_encode($signature);
    }

    private function verifyRsa(string $data, string $base64Signature): bool
    {
        $pubKey = openssl_pkey_get_public($this->publicKeyPem);
        if ($pubKey === false) {
            return false;
        }

        $sigBytes = base64_decode($base64Signature, true);
        if ($sigBytes === false) {
            return false;
        }

        return openssl_verify($data, $sigBytes, $pubKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function post(string $path, array $param): array
    {
        $url = $this->baseUrl . $path;
        $envelope = $this->envelope($param);

        Log::info('[PayCools] outbound request', [
            'url'      => $url,
            'envelope' => $envelope,
        ]);

        $response = Http::acceptJson()
            ->timeout(15)
            ->post($url, $envelope);

        Log::info('[PayCools] response', [
            'path'   => $path,
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return is_array($response->json()) ? $response->json() : [];
    }

    private static function normalizePem(string $pem): string
    {
        return str_replace(['\\r\\n', '\\n'], "\n", trim($pem));
    }
}
