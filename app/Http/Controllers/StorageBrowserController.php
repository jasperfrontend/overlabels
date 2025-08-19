<?php

namespace App\Http\Controllers;

use App\Models\StorageAccount;
use App\Services\Storage\StorageServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class StorageBrowserController extends Controller
{
    public function listFiles(Request $request, StorageAccount $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'path' => 'nullable|string',
            'cursor' => 'nullable|string',
        ]);

        try {
            $service = StorageServiceFactory::create($account);
            
            $result = $service->listFiles(
                $request->input('path', ''),
                $request->input('cursor')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Storage browse error', [
                'account_id' => $account->id,
                'provider' => $account->provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load files: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getFile(Request $request, StorageAccount $account, string $fileId)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $service = StorageServiceFactory::create($account);
            $file = $service->getFile($fileId);

            return response()->json([
                'success' => true,
                'data' => $file,
            ]);
        } catch (Exception $e) {
            Log::error('Storage get file error', [
                'account_id' => $account->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getDownloadUrl(Request $request, StorageAccount $account, string $fileId)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $service = StorageServiceFactory::create($account);
            $url = $service->getDownloadUrl($fileId);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Storage download URL error', [
                'account_id' => $account->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get download URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getShareableUrl(Request $request, StorageAccount $account, string $fileId)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $service = StorageServiceFactory::create($account);
            $url = $service->getShareableUrl($fileId);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Storage shareable URL error', [
                'account_id' => $account->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get shareable URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getThumbnail(Request $request, StorageAccount $account, string $fileId)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $service = StorageServiceFactory::create($account);
            $url = $service->getThumbnailUrl($fileId);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Storage thumbnail error', [
                'account_id' => $account->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get thumbnail: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getQuota(Request $request, StorageAccount $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $service = StorageServiceFactory::create($account);
            $quota = $service->getQuota();

            return response()->json([
                'success' => true,
                'data' => $quota,
            ]);
        } catch (Exception $e) {
            Log::error('Storage quota error', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get quota: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function validateConnection(Request $request, StorageAccount $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $service = StorageServiceFactory::create($account);
            $isValid = $service->validateConnection();

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Storage validation error', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Connection validation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}