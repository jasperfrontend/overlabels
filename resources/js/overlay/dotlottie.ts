// The <dotlottie-wc> element for overlays, served from our own build.
//
// This used to be a <script> from unpkg.com on every overlay page, and the
// player then fetched its WASM renderer from cdn.jsdelivr.net (unpkg as the
// fallback): two third parties running in every streamer's OBS, each able to
// break or alter every overlay at once, and each seeing every viewer's IP.
//
// ORDER IS LOAD-BEARING. Defining <dotlottie-wc> upgrades every element already
// in the page on the spot, and that upgrade starts the renderer download. So
// the WASM URL must be set BEFORE the element is defined, which is why the
// element arrives through a dynamic import() below and not a static import: the
// import sorter reorders static imports, and with the element first the
// renderer silently comes from jsdelivr again. Verified in Firefox both ways.
//
// The player class lives in a hashed chunk because the package bundles
// dotlottie-web into its dist. The version is pinned exactly in package.json,
// and a bump that renames the chunk fails the build here, loudly.
// @ts-expect-error - an internal chunk of the package, it ships no types
import { a as DotLottie, b as DotLottieWorker } from '@lottiefiles/dotlottie-wc/dist/chunk-ZSSL66PV.js';
import wasmUrl from '@lottiefiles/dotlottie-web/dist/dotlottie-player.wasm?url';

DotLottie.setWasmUrl(wasmUrl);
DotLottieWorker.setWasmUrl(wasmUrl);

void import('@lottiefiles/dotlottie-wc');
