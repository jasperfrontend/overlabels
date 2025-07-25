<?php

namespace App\Http\Controllers;

use App\Models\Fox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Cloudinary\Cloudinary;

class FoxController extends Controller
{
    private function getCloudinary()
    {
        return new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ]
        ]);
    }

    public function index(Request $request)
    {
        try {
            // Step 1: Fetch from API
            $response = Http::get('https://randomfox.ca/floof/');
            $url = $response->json()['image'];

            $info = pathinfo($url);
            $filename = $info['basename'];
            $publicId = 'foxes/' . pathinfo($filename, PATHINFO_FILENAME); // Remove extension, Cloudinary will handle it

            // Step 2: Check if we already have this fox
            $existingFox = Fox::where('api_url', $url)->first();
            if ($existingFox && $existingFox->cloudinary_url) {
                return Inertia::render('Fox', [
                    'foxPic' => $existingFox->cloudinary_url,
                ]);
            }

            // Step 3: Upload to Cloudinary
            $cloudinary = $this->getCloudinary();
            
            $uploadResult = $cloudinary->uploadApi()->upload($url, [
                'public_id' => $publicId,
                'folder' => 'foxes',
                'transformation' => [
                    'width' => 640,
                    'height' => 480,
                    'crop' => 'auto', // AI-powered smart cropping
                    'gravity' => 'auto', // Auto-detect most important part
                    'quality' => 'auto',
                    'fetch_format' => 'auto'
                ],
                'overwrite' => false, // Don't overwrite if already exists
                'resource_type' => 'image'
            ]);

            $cloudinaryUrl = $uploadResult['secure_url'];

            // Step 4: Save/Update in database
            $fox = Fox::updateOrCreate(
                ['api_url' => $url],
                [
                    'local_file' => $filename,
                    'cloudinary_url' => $cloudinaryUrl,
                    'cloudinary_public_id' => $uploadResult['public_id']
                ]
            );

            return Inertia::render('Fox', [
                'foxPic' => $cloudinaryUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Fox controller error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Fallback to external URL if Cloudinary fails
            $response = Http::get('https://randomfox.ca/floof/');
            $fallbackUrl = $response->json()['image'] ?? 'https://images.unsplash.com/photo-1494947665470-20322015e3a8?w=640';
            
            return Inertia::render('Fox', [
                'foxPic' => $fallbackUrl,
            ]);
        }
    }

    public function gallery()
    {
        $foxes = Fox::orderByDesc('created_at')->paginate(20);
        return Inertia::render('FoxGallery', [
            'foxes' => $foxes,
        ]);
    }
}