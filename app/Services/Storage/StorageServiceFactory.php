<?php

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Exception;

class StorageServiceFactory
{
    public static function create(StorageAccount $account): StorageServiceInterface
    {
        return match ($account->provider) {
            'google_drive' => new GoogleDriveService($account),
            'onedrive' => new OneDriveService($account),
            'dropbox' => new DropboxService($account),
            default => throw new Exception("Unsupported storage provider: {$account->provider}"),
        };
    }
}