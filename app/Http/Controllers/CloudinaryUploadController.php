<?php

namespace App\Http\Controllers;

use App\Models\CloudinaryUpload;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Services\CloudinaryUploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CloudinaryUploadController extends Controller
{
    public function __construct(private readonly CloudinaryUploadService $service) {}

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:10240'],
            'kind' => ['required', 'in:'.CloudinaryUpload::KIND_TEMPLATE_SCREENSHOT.','.CloudinaryUpload::KIND_KIT_THUMBNAIL],
            'replaces_url' => ['nullable', 'url', 'max:2048'],
        ]);

        try {
            $upload = $this->service->upload(
                $request->file('image'),
                $request->user(),
                $validated['kind'],
            );
        } catch (ValidationException $e) {
            // Service-side validation (e.g. min dimensions) - let Laravel
            // render the standard 422 JSON so the frontend can surface the
            // actual reason ("Image must be at least 400x400px") instead of
            // the generic 500 fallback below.
            throw $e;
        } catch (Exception $e) {
            Log::error('Cloudinary upload failed', [
                'user_id' => $request->user()?->id,
                'kind' => $validated['kind'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Upload failed. Please try again.',
            ], 500);
        }

        // Replace flow: the dropzone sent us the URL it was previously holding,
        // which the user is now discarding in favor of the new upload. Delete
        // the old asset, but exclude the user's own template/kit that still
        // points at it (the parent persist call will overwrite that field
        // moments later). Forks/other refs keep the existing share guard.
        if (! empty($validated['replaces_url'])) {
            $previousUrl = $validated['replaces_url'];
            $userId = $request->user()->id;

            if ($validated['kind'] === CloudinaryUpload::KIND_TEMPLATE_SCREENSHOT) {
                $excludeTemplateId = OverlayTemplate::where('owner_id', $userId)
                    ->where('screenshot_url', $previousUrl)
                    ->value('id');
                $this->service->deleteByUrl($previousUrl, excludeTemplateId: $excludeTemplateId);
            } else {
                $excludeKitId = Kit::where('owner_id', $userId)
                    ->where('thumbnail', $previousUrl)
                    ->value('id');
                $this->service->deleteByUrl($previousUrl, excludeKitId: $excludeKitId);
            }
        }

        return response()->json([
            'url' => $upload->secure_url,
            'width' => $upload->width,
            'height' => $upload->height,
        ]);
    }
}
