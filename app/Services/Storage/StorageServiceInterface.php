<?php

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Illuminate\Support\Collection;

interface StorageServiceInterface
{
    public function __construct(StorageAccount $account);
    
    public function listFiles(string $path = '', ?string $cursor = null): array;
    
    public function getFile(string $fileId): array;
    
    public function getDownloadUrl(string $fileId): string;
    
    public function getShareableUrl(string $fileId): string;
    
    public function getThumbnailUrl(string $fileId): ?string;
    
    public function refreshToken(): void;
    
    public function validateConnection(): bool;
    
    public function getQuota(): array;
}