<?php

namespace App\Http\Controllers;

use App\Models\Fox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FoxController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Step 1: Fetch from API
            Log::info('Fetching fox from API...');
            $response = Http::get('https://randomfox.ca/floof/');
            $url = $response->json()['image'];
            Log::info('Fox API URL: ' . $url);

            $info = pathinfo($url);
            $file = $info['basename'];
            Log::info('File basename: ' . $file);

            // Step 2: Save to database but use external URL
            Log::info('Saving to database...');
            $fox = Fox::updateOrCreate(
                ['api_url' => $url],
                ['local_file' => $file] // Keep this for reference
            );
            Log::info('Fox saved with ID: ' . $fox->id);

            // Step 3: Use the original API URL instead of local storage
            Log::info('Using external URL: ' . $url);

            return Inertia::render('Fox', [
                'foxPic' => $url, // Use external URL directly
            ]);

        } catch (\Exception $e) {
            Log::error('Fox controller error: ' . $e->getMessage());
            
            // Return a fallback
            return Inertia::render('Fox', [
                'foxPic' => 'https://images.unsplash.com/photo-1494947665470-20322015e3a8?w=640',
            ]);
        }
    }

    public function gallery()
    {
        $foxes = Fox::orderByDesc('created_at')->paginate(20);
        
        // Transform the data to use external URLs
        $foxes->getCollection()->transform(function ($fox) {
            $fox->display_url = $fox->api_url; // Use the original API URL
            return $fox;
        });
        
        return Inertia::render('FoxGallery', [
            'foxes' => $foxes,
        ]);
    }
}