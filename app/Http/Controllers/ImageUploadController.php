<?php

namespace App\Http\Controllers;

use App\Models\ImageUpload;
use App\Services\ImageUploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ImageUploadController extends Controller
{
    public function __construct(private readonly ImageUploadService $service) {}

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:10240'],
            'kind' => ['required', 'in:'.ImageUpload::KIND_TEMPLATE_SCREENSHOT.','.ImageUpload::KIND_KIT_THUMBNAIL],
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
            Log::error('Image upload failed', [
                'user_id' => $request->user()?->id,
                'kind' => $validated['kind'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Upload failed. Please try again.',
            ], 500);
        }

        return response()->json([
            'url' => $upload->url,
            'width' => $upload->width,
            'height' => $upload->height,
        ]);
    }
}
