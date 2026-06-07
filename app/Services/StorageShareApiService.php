<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StorageShareApiService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $path, array $payload = []): Response
    {
        return Http::withToken($this->token())->post($this->url($path), $payload);
    }

    public function get(string $path): Response
    {
        return Http::withToken($this->token())->get($this->url($path));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, notification_id?: mixed, user_error?: string}
     */
    public function distributePhotoForWorkflow(array $payload, string $logContext = 'PhotoProcess'): array
    {
        $result = $this->distributePhoto($payload, $logContext);

        if ($result['success']) {
            return [
                'success' => true,
                'notification_id' => $result['data']['notification_id'] ?? null,
            ];
        }

        return [
            'success' => false,
            'user_error' => $result['error_message'] === null
                ? 'Kon geen verbinding maken met de API.'
                : 'Er is iets misgegaan bij het aanroepen van de API: '.$result['error_message'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data?: array<string, mixed>|null, error_message?: string|null}
     */
    public function distributePhoto(array $payload, string $logContext = 'PhotoProcess'): array
    {
        return $this->executePost('/photo-process/distribute', $payload, $logContext);
    }

    /**
     * @return array{success: true, rooms: array<int, mixed>}|array{success: false, message: string, status_code: int}
     */
    public function getSpaceRooms(string|int $externalId): array
    {
        try {
            $response = $this->get('/spaces/'.$externalId.'/rooms');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success'] && isset($data['rooms'])) {
                    return [
                        'success' => true,
                        'rooms' => $data['rooms'],
                    ];
                }

                return [
                    'success' => true,
                    'rooms' => $data['rooms'] ?? [],
                    'message' => $data['message'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => 'API fout: '.$response->status(),
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('PhotoProcess: API error fetching rooms', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Fout bij verbinden met API.',
                'status_code' => 500,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyStickerPlanned(string $notificationId, array $payload): void
    {
        try {
            $this->post('/photo-process/'.$notificationId.'/sticker-planned', $payload);
        } catch (\Exception $e) {
            Log::error('PhotoProcess: Failed to notify API of sticker task', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data?: array<string, mixed>|null, error_message?: string|null}
     */
    private function executePost(string $path, array $payload, string $logContext): array
    {
        try {
            $response = $this->post($path, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error("{$logContext}: API call failed", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error_message' => $response->json('message', 'Onbekende fout'),
            ];
        } catch (\Exception $e) {
            Log::error("{$logContext}: API connection error", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => null,
            ];
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) Config::get('services.storage_share_api.url'), '/').'/'.ltrim($path, '/');
    }

    private function token(): string
    {
        return (string) Config::get('services.storage_share_api.token');
    }
}
