<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dev-only tile-map builder. Paints room cells visually and writes the
 * result to resources/js/rooms/{n}.json so git handles versioning.
 *
 * Guarded three ways: env=local, admin.role middleware, and an explicit
 * env assertion in every action. Room number and folder names are
 * validated as small positive integers / a fixed allowlist of subfolder
 * names to keep File::get / File::put paths bounded.
 */
class RoomBuilderController extends Controller
{
    private const MAX_ROOM = 99;

    private const MAX_DIMENSION = 64;

    private const ASSET_SUBFOLDERS = ['tiles', 'objects', 'sounds'];

    /**
     * GET /dev/room-builder/{room}
     */
    public function show(int $room): Response
    {
        $this->assertDevOnly();
        $this->assertValidRoom($room);

        $existing = $this->readRoomFile($room);

        return Inertia::render('gamejam/builder', [
            'room' => $room,
            'roomFile' => $existing,
            'defaultWidth' => 11,
            'defaultHeight' => 11,
        ]);
    }

    /**
     * GET /dev/room-builder/{room}/assets
     *
     * Returns a manifest of available asset files under
     * public/rooms/{room}/{tiles|objects|sounds}/
     */
    public function assets(int $room): JsonResponse
    {
        $this->assertDevOnly();
        $this->assertValidRoom($room);

        $manifest = [];
        foreach (self::ASSET_SUBFOLDERS as $sub) {
            $manifest[$sub] = $this->scanAssetFolder($room, $sub);
        }

        return response()->json([
            'room' => $room,
            'assets' => $manifest,
        ]);
    }

    /**
     * POST /dev/room-builder/{room}
     *
     * Accepts the full RoomFile shape and writes it to
     * resources/js/rooms/{room}.json, overwriting any previous version.
     */
    public function save(Request $request, int $room): JsonResponse
    {
        $this->assertDevOnly();
        $this->assertValidRoom($room);

        $data = $request->validate([
            'tileset' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/i'],
            'width' => ['required', 'integer', 'min:1', 'max:'.self::MAX_DIMENSION],
            'height' => ['required', 'integer', 'min:1', 'max:'.self::MAX_DIMENSION],
            'cells' => ['required', 'array'],
            'cells.*' => ['array'],
            'cells.*.*' => ['nullable', 'array'],
            'cells.*.*.bg' => ['nullable', 'string', 'max:512'],
            'cells.*.*.overlay' => ['nullable', 'string', 'max:512'],
            'cells.*.*.trigger' => ['nullable', 'array'],
            'cells.*.*.trigger.sound' => ['nullable', 'string', 'max:512'],
            // Room-level visual layer settings. Filter is a CSS filter string
            // (e.g. "hue-rotate(180deg) saturate(1.2)"), overlayColor is a hex
            // color, overlayOpacity is a 0-1 float. All optional; empty/zero
            // means "no extra layer". Regex whitelists just the chars needed
            // for CSS filter functions so nothing can inject other rules.
            'filter' => ['nullable', 'string', 'max:256', 'regex:/^[a-zA-Z0-9\s\(\)%,\.\-]*$/'],
            'overlayColor' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/'],
            'overlayOpacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'version' => ['required', Rule::in([1])],
        ]);

        // Reject any cell asset path that tries to escape the public/rooms tree.
        $this->assertAssetPaths($data['cells']);

        $data['room'] = $room;

        $dir = resource_path('js/rooms');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.$room.'.json';
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return response()->json([
            'ok' => true,
            'path' => 'resources/js/rooms/'.$room.'.json',
            'bytes' => filesize($path) ?: 0,
        ]);
    }

    private function assertDevOnly(): void
    {
        abort_unless(app()->environment('local'), 404);
    }

    private function assertValidRoom(int $room): void
    {
        abort_unless($room >= 1 && $room <= self::MAX_ROOM, 404);
    }

    /**
     * @return array<int, array{name: string, path: string, size: int}>
     */
    private function scanAssetFolder(int $room, string $subfolder): array
    {
        $abs = public_path('rooms'.DIRECTORY_SEPARATOR.$room.DIRECTORY_SEPARATOR.$subfolder);
        if (! File::isDirectory($abs)) {
            return [];
        }

        $files = collect(File::files($abs))
            ->filter(fn ($f) => ! str_starts_with($f->getFilename(), '.'))
            ->sortBy(fn ($f) => strnatcasecmp($f->getFilename(), '') === 0 ? $f->getFilename() : $f->getFilename())
            ->values();

        return $files->map(fn ($f) => [
            'name' => $f->getFilename(),
            'path' => '/rooms/'.$room.'/'.$subfolder.'/'.$f->getFilename(),
            'size' => $f->getSize(),
        ])->all();
    }

    private function readRoomFile(int $room): ?array
    {
        $path = resource_path('js'.DIRECTORY_SEPARATOR.'rooms'.DIRECTORY_SEPARATOR.$room.'.json');
        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Refuse anything that does not start with /rooms/ so the saved JSON
     * cannot reference arbitrary URLs or traversal paths.
     */
    private function assertAssetPaths(array $cells): void
    {
        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                if (! is_array($cell)) {
                    continue;
                }
                foreach (['bg', 'overlay'] as $field) {
                    if (isset($cell[$field]) && ! $this->isAllowedAssetPath($cell[$field])) {
                        abort(422, 'Disallowed asset path: '.$cell[$field]);
                    }
                }
                if (isset($cell['trigger']['sound']) && ! $this->isAllowedAssetPath($cell['trigger']['sound'])) {
                    abort(422, 'Disallowed trigger path: '.$cell['trigger']['sound']);
                }
            }
        }
    }

    private function isAllowedAssetPath(string $path): bool
    {
        return str_starts_with($path, '/rooms/') && ! str_contains($path, '..');
    }
}
