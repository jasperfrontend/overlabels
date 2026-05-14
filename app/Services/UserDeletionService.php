<?php

namespace App\Services;

use App\Models\ExternalIntegration;
use App\Models\Kit;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserDeletionService
{
    /**
     * Hard-delete a user and everything they own. Used by both the admin
     * "delete_all" strategy and the self-serve account deletion on
     * /settings/account. Caller is responsible for audit logging and for
     * making sure the user is not the ghost user.
     */
    public function eraseAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            Kit::where('owner_id', $user->id)->each(function ($kit) {
                $kit->templates()->detach();
                $kit->delete();
            });

            OverlayTemplate::where('owner_id', $user->id)->each(function ($template) {
                $template->targetStaticOverlays()->detach();
                $template->kits()->detach();
                $template->controls()->delete();
                $template->eventMappings()->delete();
                $template->delete();
            });

            OverlayControl::where('user_id', $user->id)->delete();
            TemplateTag::where('user_id', $user->id)->delete();
            TemplateTagCategory::where('user_id', $user->id)->delete();
            ExternalIntegration::where('user_id', $user->id)->delete();

            $user->overlayAccessTokens()->delete();
            $user->eventsubSubscriptions()->delete();

            $user->forceDelete();
        });
    }
}
