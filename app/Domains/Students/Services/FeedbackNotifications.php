<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackOperation;
use App\Domains\Students\Models\Student;

class FeedbackNotifications
{
    public function send(Administrator|Student $actor, FeedbackConversation $conversation, FeedbackOperation $operation): void
    {
        $key = 'feedback:operation:'.$operation->id;
        if ($actor instanceof Student) {
            app(AdminNotificationService::class)->sendFeedback($key, $conversation->id, (int) $conversation->student_id);

            return;
        }
        app(StudentNotificationService::class)->synchronize($conversation->student, [[
            'key' => $key, 'type' => 'feedback', 'title' => 'Feedback updated',
            'message' => 'Your tutor has updated your feedback conversation.',
            'link' => route('student.feedback.show', $conversation->id, false),
        ]]);
    }
}
