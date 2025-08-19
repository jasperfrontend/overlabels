<?php

namespace App\Http\Controllers;

use App\Models\StorageAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Google\Provider as GoogleProvider;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use SocialiteProviders\Dropbox\Provider as DropboxProvider;
use Inertia\Inertia;
use Exception;

class StorageConnectionController extends Controller
{
    public function index()
    {
        $accounts = Auth::user()->storageAccounts()
            ->where('is_active', true)
            ->orderBy('provider')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'provider' => $account->provider,
                    'provider_display_name' => $account->getProviderDisplayName(),
                    'email' => $account->email,
                    'name' => $account->name,
                    'created_at' => $account->created_at,
                    'token_expires_at' => $account->token_expires_at,
                    'needs_refresh' => $account->needsTokenRefresh(),
                ];
            });

        return Inertia::render('Storage/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function connect(Request $request, string $provider)
    {
        $request->validate([
            'provider' => 'required|in:google_drive,onedrive,dropbox'
        ]);

        try {
            $config = $this->getProviderConfig($provider);
            
            $socialiteProvider = Socialite::driver($config['driver'])
                ->scopes($config['scopes']);
            
            if ($provider === 'google_drive') {
                $socialiteProvider = $socialiteProvider->with(['access_type' => 'offline']);
            }
            
            return $socialiteProvider->redirect();
        } catch (Exception $e) {
            return redirect()->back()->withErrors([
                'provider' => 'Failed to initiate connection: ' . $e->getMessage()
            ]);
        }
    }

    public function callback(Request $request, string $provider)
    {
        try {
            $config = $this->getProviderConfig($provider);
            $socialiteUser = Socialite::driver($config['driver'])->user();
            
            $existingAccount = StorageAccount::where('user_id', Auth::id())
                ->where('provider', $provider)
                ->where('provider_user_id', $socialiteUser->getId())
                ->first();

            if ($existingAccount) {
                $existingAccount->updateTokens(
                    $socialiteUser->token,
                    $socialiteUser->refreshToken,
                    $socialiteUser->expiresIn ? Carbon::now()->addSeconds($socialiteUser->expiresIn) : null
                );
                $existingAccount->update([
                    'email' => $socialiteUser->getEmail(),
                    'name' => $socialiteUser->getName(),
                    'is_active' => true,
                ]);
            } else {
                StorageAccount::create([
                    'user_id' => Auth::id(),
                    'provider' => $provider,
                    'provider_user_id' => $socialiteUser->getId(),
                    'email' => $socialiteUser->getEmail(),
                    'name' => $socialiteUser->getName(),
                    'access_token' => $socialiteUser->token,
                    'refresh_token' => $socialiteUser->refreshToken,
                    'token_expires_at' => $socialiteUser->expiresIn ? Carbon::now()->addSeconds($socialiteUser->expiresIn) : null,
                    'scopes' => $config['scopes'],
                    'is_active' => true,
                ]);
            }

            return redirect()->route('storage.index')->with('success', 
                ucfirst($provider) . ' account connected successfully!'
            );
        } catch (Exception $e) {
            return redirect()->route('storage.index')->withErrors([
                'callback' => 'Failed to connect account: ' . $e->getMessage()
            ]);
        }
    }

    public function disconnect(Request $request, StorageAccount $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $account->update(['is_active' => false]);

        return redirect()->back()->with('success', 
            $account->getProviderDisplayName() . ' account disconnected successfully!'
        );
    }

    public function destroy(StorageAccount $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $account->delete();

        return redirect()->back()->with('success', 
            $account->getProviderDisplayName() . ' account removed successfully!'
        );
    }

    private function getProviderConfig(string $provider): array
    {
        return match ($provider) {
            'google_drive' => [
                'driver' => 'google',
                'scopes' => ['https://www.googleapis.com/auth/drive.readonly'],
            ],
            'onedrive' => [
                'driver' => 'microsoft',
                'scopes' => ['https://graph.microsoft.com/Files.Read.All'],
            ],
            'dropbox' => [
                'driver' => 'dropbox',
                'scopes' => ['files.metadata.read', 'files.content.read', 'sharing.read'],
            ],
            default => throw new Exception("Unsupported provider: {$provider}"),
        };
    }
}