<?php

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Exception;

class DropboxService implements StorageServiceInterface
{
    private StorageAccount $account;
    private string $apiUrl = 'https://api.dropboxapi.com/2';
    private string $contentUrl = 'https://content.dropboxapi.com/2';

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
            if ($cursor) {
                $response = Http::withHeaders($this->getHeaders())
                    ->post("{$this->apiUrl}/files/list_folder/continue", [
                        'cursor' => $cursor,
                    ]);
            } else {
                $response = Http::withHeaders($this->getHeaders())
                    ->post("{$this->apiUrl}/files/list_folder", [
                        'path' => $path ?: '',
                        'recursive' => false,
                        'include_media_info' => true,
                        'include_deleted' => false,
                        'include_has_explicit_shared_members' => false,
                        'limit' => 50,
                    ]);
            }
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            
            $files = collect($data['entries'] ?? [])->map(function ($entry) {
                $isFolder = $entry['.tag'] === 'folder';
                $isImage = false;
                $isVideo = false;
                
                if (isset($entry['media_info'])) {
                    $mediaTag = $entry['media_info']['.tag'] ?? '';
                    $isImage = $mediaTag === 'photo';
                    $isVideo = $mediaTag === 'video';
                }
                
                return [
                    'id' => $entry['id'],
                    'name' => $entry['name'],
                    'type' => $isFolder ? 'folder' : 'file',
                    'path' => $entry['path_display'],
                    'size' => $entry['size'] ?? 0,
                    'thumbnailUrl' => null,
                    'webViewLink' => null,
                    'downloadUrl' => null,
                    'modifiedAt' => isset($entry['client_modified']) ? Carbon::parse($entry['client_modified']) : null,
                    'isImage' => $isImage,
                    'isVideo' => $isVideo,
                ];
            })->toArray();
            
            foreach ($files as &$file) {
                if ($file['type'] === 'file') {
                    if ($file['isImage']) {
                        $file['thumbnailUrl'] = $this->getThumbnailUrl($file['id']);
                    }
                }
            }
            
            return [
                'files' => $files,
                'nextCursor' => $data['cursor'] ?? null,
                'hasMore' => $data['has_more'] ?? false,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to list Dropbox files: ' . $e->getMessage());
        }
    }

    public function getFile(string $fileId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->apiUrl}/files/get_metadata", [
                    'path' => $fileId,
                    'include_media_info' => true,
                ]);
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $entry = $response->json();
            
            $isImage = false;
            $isVideo = false;
            
            if (isset($entry['media_info'])) {
                $mediaTag = $entry['media_info']['.tag'] ?? '';
                $isImage = $mediaTag === 'photo';
                $isVideo = $mediaTag === 'video';
            }
            
            return [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'path' => $entry['path_display'],
                'size' => $entry['size'] ?? 0,
                'thumbnailUrl' => $isImage ? $this->getThumbnailUrl($entry['id']) : null,
                'webViewLink' => null,
                'downloadUrl' => $this->getDownloadUrl($entry['id']),
                'modifiedAt' => isset($entry['client_modified']) ? Carbon::parse($entry['client_modified']) : null,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get Dropbox file: ' . $e->getMessage());
        }
    }

    public function getDownloadUrl(string $fileId): string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->apiUrl}/files/get_temporary_link", [
                    'path' => $fileId,
                ]);
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            
            return $data['link'] ?? '';
        } catch (Exception $e) {
            throw new Exception('Failed to get download URL: ' . $e->getMessage());
        }
    }

    public function getShareableUrl(string $fileId): string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->apiUrl}/sharing/create_shared_link_with_settings", [
                    'path' => $fileId,
                    'settings' => [
                        'requested_visibility' => 'public',
                        'audience' => 'public',
                    ],
                ]);
            
            if ($response->status() === 409) {
                $response = Http::withHeaders($this->getHeaders())
                    ->post("{$this->apiUrl}/sharing/list_shared_links", [
                        'path' => $fileId,
                    ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['links']) && count($data['links']) > 0) {
                        return $data['links'][0]['url'] ?? '';
                    }
                }
            }
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            
            return $data['url'] ?? '';
        } catch (Exception $e) {
            throw new Exception('Failed to create shareable link: ' . $e->getMessage());
        }
    }

    public function getThumbnailUrl(string $fileId): ?string
    {
        try {
            $headers = $this->getHeaders();
            $headers['Dropbox-API-Arg'] = json_encode([
                'path' => $fileId,
                'format' => 'jpeg',
                'size' => 'w256h256',
                'mode' => 'strict',
            ]);
            
            $response = Http::withHeaders($headers)
                ->post("{$this->contentUrl}/files/get_thumbnail_v2");
            
            if (!$response->successful()) {
                return null;
            }
            
            return 'data:image/jpeg;base64,' . base64_encode($response->body());
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
            
            $response = Http::asForm()->post('https://api.dropbox.com/oauth2/token', [
                'client_id' => config('services.dropbox.client_id'),
                'client_secret' => config('services.dropbox.client_secret'),
                'refresh_token' => $this->account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $this->account->updateTokens(
                    $data['access_token'],
                    $this->account->refresh_token,
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
                ->post("{$this->apiUrl}/users/get_current_account");
            
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getQuota(): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->apiUrl}/users/get_space_usage");
            
            if (!$response->successful()) {
                throw new Exception('API request failed: ' . $response->body());
            }
            
            $data = $response->json();
            
            return [
                'used' => $data['used'] ?? 0,
                'total' => $data['allocation']['allocated'] ?? 0,
                'unlimited' => false,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get quota: ' . $e->getMessage());
        }
    }
}