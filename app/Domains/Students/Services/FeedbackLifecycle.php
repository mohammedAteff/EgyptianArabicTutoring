<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\FeedbackConversation;
use Illuminate\Support\Facades\DB;

class FeedbackLifecycle
{
    /** Called only after the existing lifecycle operation locks both parent Students. */
    public function merge(int $primaryId, int $secondaryId): void
    {
        $this->requireTransaction();
        $ids = FeedbackConversation::query()->where('student_id', $secondaryId)->orderBy('id')->lockForUpdate()->pluck('id');
        DB::table('feedback_conversations')->whereIn('id', $ids)->update(['student_id' => $primaryId]);
        DB::table('feedback_operations')->whereIn('conversation_id', $ids)->update(['student_id' => $primaryId]);
        DB::table('feedback_read_states')->whereIn('conversation_id', $ids)->where('actor_type', 'student')->where('actor_id', $secondaryId)->update(['actor_id' => $primaryId]);
        DB::table('admin_notifications')->where('type', 'feedback')->where('data->student_id', $secondaryId)->update(['data' => DB::raw("JSON_SET(data, '$.student_id', ".(int) $primaryId.')')]);
    }

    /** Parent Student remains locked throughout redaction, including quoted tutor messages. */
    public function erase(int $studentId): void
    {
        $this->requireTransaction();
        $ids = FeedbackConversation::query()->where('student_id', $studentId)->orderBy('id')->lockForUpdate()->pluck('id');
        DB::table('feedback_conversations')->whereIn('id', $ids)->update([
            'subject' => 'Redacted feedback', 'context_type' => null, 'context_id' => null, 'erased_at' => now('UTC'),
        ]);
        DB::table('feedback_events')->whereIn('conversation_id', $ids)->update(['body' => null, 'actor_id' => null]);
        DB::table('feedback_operations')->whereIn('conversation_id', $ids)->delete();
        DB::table('feedback_read_states')->whereIn('conversation_id', $ids)->delete();
        DB::table('admin_notifications')->where('type', 'feedback')->where('data->student_id', $studentId)->delete();
        DB::table('student_notifications')->where('type', 'feedback')->where('student_id', $studentId)->delete();
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Feedback lifecycle requires canonical Student locks.');
        }
    }
}
