<?php

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Http;
use Exception;

class GoogleDriveService implements StorageServiceInterface
{
    private StorageAccount $account;
    private Client $client;
    private Drive $service;

    public function __construct(StorageAccount $account)
    {
        $this->account = $account;
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setAccessToken($this->account->access_token);
        
        if ($this->account->refresh_token) {
            $this->client->setRefreshToken($this->account->refresh_token);
        }
        
        if ($this->account->needsTokenRefresh()) {
            $this->refreshToken();
        }
        
        $this->service = new Drive($this->client);
    }

    public function listFiles(string $path = '', ?string $cursor = null): array
    {
        try {
            $query = "trashed = false";
            
            if ($path) {
                $query .= " and '{$path}' in parents";
            } else {
                $query .= " and 'root' in parents";
            }
            
            $parameters = [
                'q' => $query,
                'fields' => 'nextPageToken, files(id, name, mimeType, size, thumbnailLink, webViewLink, webContentLink, modifiedTime, iconLink, parents)',
                'pageSize' => 50,
                'orderBy' => 'folder,name',
            ];
            
            if ($cursor) {
                $parameters['pageToken'] = $cursor;
            }
            
            $results = $this->service->files->listFiles($parameters);
            
            $files = collect($results->getFiles())->map(function ($file) {
                return [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'type' => strpos($file->getMimeType(), 'folder') !== false ? 'folder' : 'file',
                    'mimeType' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'thumbnailUrl' => $file->getThumbnailLink(),
                    'webViewLink' => $file->getWebViewLink(),
                    'downloadUrl' => $file->getWebContentLink(),
                    'modifiedAt' => $file->getModifiedTime() ? Carbon::parse($file->getModifiedTime()) : null,
                    'iconUrl' => $file->getIconLink(),
                    'isImage' => strpos($file->getMimeType(), 'image/') === 0,
                    'isVideo' => strpos($file->getMimeType(), 'video/') === 0,
                ];
            })->toArray();
            
            return [
                'files' => $files,
                'nextCursor' => $results->getNextPageToken(),
                'hasMore' => !empty($results->getNextPageToken()),
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to list Google Drive files: ' . $e->getMessage());
        }
    }

    public function getFile(string $fileId): array
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id, name, mimeType, size, thumbnailLink, webViewLink, webContentLink, modifiedTime, iconLink'
            ]);
            
            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'thumbnailUrl' => $file->getThumbnailLink(),
                'webViewLink' => $file->getWebViewLink(),
                'downloadUrl' => $file->getWebContentLink(),
                'modifiedAt' => $file->getModifiedTime() ? Carbon::parse($file->getModifiedTime()) : null,
                'iconUrl' => $file->getIconLink(),
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get Google Drive file: ' . $e->getMessage());
        }
    }

    public function getDownloadUrl(string $fileId): string
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'webContentLink'
            ]);
            
            return $file->getWebContentLink() ?? '';
        } catch (Exception $e) {
            throw new Exception('Failed to get download URL: ' . $e->getMessage());
        }
    }

    public function getShareableUrl(string $fileId): string
    {
        try {
            $permission = new \Google\Service\Drive\Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            
            $this->service->permissions->create($fileId, $permission);
            
            $file = $this->service->files->get($fileId, [
                'fields' => 'webViewLink'
            ]);
            
            return $file->getWebViewLink() ?? '';
        } catch (Exception $e) {
            throw new Exception('Failed to get shareable URL: ' . $e->getMessage());
        }
    }

    public function getThumbnailUrl(string $fileId): ?string
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'thumbnailLink'
            ]);
            
            $thumbnailLink = $file->getThumbnailLink();
            
            if ($thumbnailLink) {
                return str_replace('=s220', '=s400', $thumbnailLink);
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
            
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
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
                
                $this->client->setAccessToken($data['access_token']);
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
            $this->service->about->get(['fields' => 'user']);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getQuota(): array
    {
        try {
            $about = $this->service->about->get([
                'fields' => 'storageQuota'
            ]);
            
            $quota = $about->getStorageQuota();
            
            return [
                'used' => $quota->getUsage(),
                'total' => $quota->getLimit(),
                'unlimited' => $quota->getLimit() === null,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get quota: ' . $e->getMessage());
        }
    }
}