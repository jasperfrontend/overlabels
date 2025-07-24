<?php

namespace App\Http\Controllers;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use App\Models\Fox;
use Illuminate\Http\Request;
use Illuminate\Http\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;


class FoxController extends Controller
{
    public function index(Request $request)
    {
        try {
            $manager = new ImageManager(new Driver());

            // Step 1: Fetch from API
            Log::info('Fetching fox from API...');
            $response = Http::get('https://randomfox.ca/floof/');
            $url = $response->json()['image'];
            Log::info('Fox API URL: ' . $url);

            $info = pathinfo($url);
            $file = $info['basename'];
            $publicDisk = Storage::disk('public');

            // Step 2: Download & resize
            Log::info('Downloading and processing image...');
            $image = $manager->read(file_get_contents($url));
            $image->scale(width: 640);

            // Step 3: Save as JPEG (of PNG, afhankelijk van $file extensie)
            $imageData = (string) $image->toJpeg(90);

            if (!$publicDisk->exists($file)) {
                Log::info('Saving file to storage: ' . $file);
                $publicDisk->put($file, $imageData);
            } else {
                Log::info('File already exists: ' . $file);
            }

            // Step 4: Save/Update in database
            Log::info('Saving to database...');
            Fox::updateOrCreate(
                ['api_url' => $url],
                ['local_file' => $file]
            );

            $uploaded_file = asset('storage/' . $file);
            Log::info('Final asset URL: ' . $uploaded_file);

            return Inertia::render('Fox', [
                'foxPic' => $uploaded_file,
            ]);

        } catch (\Exception $e) {
            Log::error('Fox controller error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return a fallback using external URL
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