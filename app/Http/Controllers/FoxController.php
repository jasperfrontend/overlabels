<?php

namespace App\Http\Controllers;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use App\Models\Fox;
use Illuminate\Http\Request;
use Illuminate\Http\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;


class FoxController extends Controller
{
    public function index(Request $request)
    {
        $manager = new ImageManager(new Driver());

        $response = Http::get('https://randomfox.ca/floof/');
        $url = $response->json()['image'];
        $info = pathinfo($url);
        $file = $info['basename'];
        $publicDisk = Storage::disk('public');

        // Download & resize
        $image = $manager->read(file_get_contents($url));
        $image->scale(width: 640);

        // Save as JPEG (of PNG, afhankelijk van $file extensie)
        $imageData = (string) $image->toJpeg(90);

        if (!$publicDisk->exists($file)) {
            $publicDisk->put($file, $imageData);
        }

        // Save/Update in database
        Fox::updateOrCreate(
            ['api_url' => $url],
            ['local_file' => $file]
        );

        $uploaded_file = asset('storage/' . $file);

        return Inertia::render('Fox', [
            'foxPic' => $uploaded_file,
        ]);
    }

    public function gallery()
    {
        $foxes = Fox::orderByDesc('created_at')->paginate(20);
        return Inertia::render('FoxGallery', [
            'foxes' => $foxes,
        ]);
    }
}