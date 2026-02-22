<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAuditService
{
    public function log(
        User $admin,
        string $action,
        ?string $targetType,
        ?int $targetId,
        array $metadata,
        Request $request
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
