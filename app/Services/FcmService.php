<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * @param  array<int, string>  $tokens
     * @param  array{title:string,body:string}  $notification
     * @param  array<string, scalar|null>  $data
     * @return array{total:int,sent:int,failed:int,invalid_tokens:array<int,string>,errors:array<int,string>}
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        $total = count($tokens);

        $result = [
            'total' => $total,
            'sent' => 0,
            'failed' => 0,
            'invalid_tokens' => [],
            'errors' => [],
        ];

        if ($total === 0) {
            return $result;
        }

        $projectId = (string) config('services.firebase.project_id');
        if ($projectId === '') {
            $result['failed'] = $total;
            $result['errors'][] = 'Firebase project_id is not configured.';

            return $result;
        }

        $accessToken = $this->accessToken();
        if (! $accessToken) {
            $result['failed'] = $total;
            $result['errors'][] = 'Unable to obtain Firebase access token.';

            return $result;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $notification['title'],
                        'body' => $notification['body'],
                    ],
                    'data' => (object) $this->normalizeData($data),
                    'android' => [
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)->post($url, $payload);

            if ($response->successful()) {
                $result['sent']++;

                continue;
            }

            $result['failed']++;
            if ($this->isInvalidTokenResponse($response->json())) {
                $result['invalid_tokens'][] = $token;
            }

            $result['errors'][] = "FCM push failed ({$response->status()}): {$response->body()}";
        }

        if ($result['failed'] > 0) {
            Log::warning('FCM sendToTokens completed with failures.', [
                'result' => $result,
            ]);
        }

        return $result;
    }

    private function accessToken(): ?string
    {
        return Cache::remember('firebase.fcm.access_token', now()->addMinutes(50), function (): ?string {
            $credentialsInput = $this->credentialsInput();
            if (! $credentialsInput) {
                return null;
            }

            try {
                $credentials = new ServiceAccountCredentials(
                    ['https://www.googleapis.com/auth/firebase.messaging'],
                    $credentialsInput
                );

                $tokenData = $credentials->fetchAuthToken();
                $token = $tokenData['access_token'] ?? null;

                return is_string($token) ? $token : null;
            } catch (\Throwable $e) {
                Log::error('Failed to fetch Firebase OAuth token.', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    private function credentialsInput(): array|string|null
    {
        $path = (string) config('services.firebase.service_account_path');
        if ($path !== '' && file_exists($path)) {
            return $path;
        }

        $rawJson = (string) config('services.firebase.service_account_json');
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, scalar|null>  $data
     * @return array<string, string>
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $normalized[(string) $key] = (string) $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function isInvalidTokenResponse(?array $body): bool
    {
        if (! is_array($body)) {
            return false;
        }

        $details = $body['error']['details'] ?? null;
        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $errorCode = $detail['errorCode'] ?? null;
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'SENDER_ID_MISMATCH'], true)) {
                return true;
            }
        }

        return false;
    }
}

