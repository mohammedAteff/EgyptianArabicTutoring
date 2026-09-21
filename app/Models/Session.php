<?php

namespace App\Models;

use App\Domains\Analytics\Models\Visitor as DomainVisitor;
use App\Domains\Analytics\Models\VisitorSession as DomainVisitorSession;

class Session extends DomainVisitorSession
{
    public function getVisitorIdAttribute(): mixed
    {
        $rawId = $this->attributes['visitor_id'] ?? null;
        if (! $rawId) {
            return null;
        }

        $token = DomainVisitor::where('id', $rawId)->value('visitor_token');

        return $token ?: (string) $rawId;
    }
}
