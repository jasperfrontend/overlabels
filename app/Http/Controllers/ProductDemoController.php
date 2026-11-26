<?php

namespace App\Http\Controllers;

use App\Services\Recipes\ProductDemo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The public demo of a chat game (ProductDemo): the page the homepage and the
 * product page frame, and the sample payload its renderer loads. No login,
 * nothing written, noindex.
 */
class ProductDemoController extends Controller
{
    public function __construct(private readonly ProductDemo $demos) {}

    public function frame(string $slug): Response
    {
        $frame = $this->demos->frame($slug);
        abort_if($frame === null, 404);

        return response()
            ->view('overlay.demo', ['product' => $frame['product'], 'name' => $frame['name']])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function payload(string $slug): JsonResponse
    {
        $payload = $this->demos->payload($slug);
        abort_if($payload === null, 404);

        return response()->json($payload)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
