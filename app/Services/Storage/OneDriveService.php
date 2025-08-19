<?php

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Exception;

class OneDriveService implements StorageServiceInterface
{
    private StorageAccount $account;
    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct(StorageAccount $account)
    {
        $this->account = $account;
        
        if ($this->account->needsTokenRefresh()) {
            $this->refreshToken();
        }
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->account->access_token,
            'Content-Type' => 'application/json',
        ];
    }

    public function listFiles(string $path = '', ?string $cursor = null): array
    {
        try {
            $endpoint = $path 
                ? "{$this->baseUrl}/me/drive/items/{$path}/children" 
                : "{$this->baseUrl}/me/drive/root/children";
            
            if ($cursor) {
                $endpoint = $cursor;
            }
            
            $response = Http::withHeaders($this->getHeaders())
                ->get($endpoint, [
                    '$select' => 'id,name,size,file,folder,image,video,webUrl,@microsoft.graph.downloadUrl,lastModifiedDateTime,thumbnails',
                    '$top' => 50,
                    '$expand' => 'thumbnails',
                ]);
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            
            $files = collect($data['value'] ?? [])->map(function ($item) {
                $isFolder = isset($item['folder']);
                $isImage = isset($item['image']);
                $isVideo = isset($item['video']);
                
                $thumbnailUrl = null;
                if (isset($item['thumbnails']) && count($item['thumbnails']) > 0) {
                    $thumbnailUrl = $item['thumbnails'][0]['large']['url'] ?? 
                                   $item['thumbnails'][0]['medium']['url'] ?? 
                                   $item['thumbnails'][0]['small']['url'] ?? null;
                }
                
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'type' => $isFolder ? 'folder' : 'file',
                    'mimeType' => $item['file']['mimeType'] ?? null,
                    'size' => $item['size'] ?? 0,
                    'thumbnailUrl' => $thumbnailUrl,
                    'webViewLink' => $item['webUrl'],
                    'downloadUrl' => $item['@microsoft.graph.downloadUrl'] ?? null,
                    'modifiedAt' => isset($item['lastModifiedDateTime']) ? Carbon::parse($item['lastModifiedDateTime']) : null,
                    'isImage' => $isImage,
                    'isVideo' => $isVideo,
                ];
            })->toArray();
            
            return [
                'files' => $files,
                'nextCursor' => $data['@odata.nextLink'] ?? null,
                'hasMore' => isset($data['@odata.nextLink']),
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to list OneDrive files: ' . $e->getMessage());
        }
    }

    public function getFile(string $fileId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/me/drive/items/{$fileId}", [
                    '$select' => 'id,name,size,file,folder,image,video,webUrl,@microsoft.graph.downloadUrl,lastModifiedDateTime,thumbnails',
                    '$expand' => 'thumbnails',
                ]);
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $item = $response->json();
            
            $thumbnailUrl = null;
            if (isset($item['thumbnails']) && count($item['thumbnails']) > 0) {
                $thumbnailUrl = $item['thumbnails'][0]['large']['url'] ?? 
                               $item['thumbnails'][0]['medium']['url'] ?? 
                               $item['thumbnails'][0]['small']['url'] ?? null;
            }
            
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'mimeType' => $item['file']['mimeType'] ?? null,
                'size' => $item['size'] ?? 0,
                'thumbnailUrl' => $thumbnailUrl,
                'webViewLink' => $item['webUrl'],
                'downloadUrl' => $item['@microsoft.graph.downloadUrl'] ?? null,
                'modifiedAt' => isset($item['lastModifiedDateTime']) ? Carbon::parse($item['lastModifiedDateTime']) : null,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get OneDrive file: ' . $e->getMessage());
        }
    }

    public function getDownloadUrl(string $fileId): string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/me/drive/items/{$fileId}", [
                    '$select' => '@microsoft.graph.downloadUrl',
                ]);
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            
            return $data['@microsoft.graph.downloadUrl'] ?? '';
        } catch (Exception $e) {
            throw new Exception('Failed to get download URL: ' . $e->getMessage());
        }
    }

    public function getShareableUrl(string $fileId): string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/me/drive/items/{$fileId}/createLink", [
                    'type' => 'view',
                    'scope' => 'anonymous',
                ]);
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            
            return $data['link']['webUrl'] ?? '';
        } catch (Exception $e) {
            throw new Exception('Failed to create shareable link: ' . $e->getMessage());
        }
    }

    public function getThumbnailUrl(string $fileId): ?string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/me/drive/items/{$fileId}/thumbnails");
            
            if (!$response->successful()) {
                return null;
            }
            
            $data = $response->json();
            
            if (isset($data['value']) && count($data['value']) > 0) {
                $thumbnail = $data['value'][0];
                return $thumbnail['large']['url'] ?? 
                       $thumbnail['medium']['url'] ?? 
                       $thumbnail['small']['url'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function refreshToken(): void
    {
        try {
            if (!$this->account->refresh_token) {
                throw new Exception('No refresh token available');
            }
            
            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'refresh_token' => $this->account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $this->account->updateTokens(
                    $data['access_token'],
                    $data['refresh_token'] ?? $this->account->refresh_token,
                    isset($data['expires_in']) ? Carbon::now()->addSeconds($data['expires_in']) : null
                );
            } else {
                throw new Exception('Failed to refresh token: ' . $response->body());
            }
        } catch (Exception $e) {
            throw new Exception('Token refresh failed: ' . $e->getMessage());
        }
    }

    public function validateConnection(): bool
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/me/drive");
            
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getQuota(): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/me/drive", [
                    '$select' => 'quota',
                ]);
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            $quota = $data['quota'] ?? [];
            
            return [
                'used' => $quota['used'] ?? 0,
                'total' => $quota['total'] ?? 0,
                'unlimited' => false,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get quota: ' . $e->getMessage());
        }
    }
}