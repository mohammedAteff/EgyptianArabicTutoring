<?php

namespace Database\Seeders;

use App\Domains\Administration\Models\StaffNoteTemplate;
use Illuminate\Database\Seeder;

class StaffNoteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'title' => 'Level Assessment & Placement Summary',
                'category' => 'teaching',
                'body' => "Student was assessed on reading, speaking fluency, and grammar fundamentals.\n- Current Level: \n- Strengths: \n- Focus Areas: \n- Recommended Curriculum Track: ",
            ],
            [
                'title' => 'Homework & Practice Follow-up',
                'category' => 'teaching',
                'body' => "Homework assignment review:\n- Assigned material: \n- Completion status: Completed / Partially / Incomplete\n- Notes on student understanding: \n- Next exercises: ",
            ],
            [
                'title' => 'Attendance & Lateness Record',
                'category' => 'attendance',
                'body' => "Attendance note:\n- Date: \n- Incident: Late arrival ( minutes) / Early departure\n- Reason communicated: \n- Impact on lesson objectives: ",
            ],
            [
                'title' => 'Parent Consultation Follow-up',
                'category' => 'administrative',
                'body' => "Parent consultation notes:\n- Guardian Contact: \n- Topics discussed: \n- Student progress feedback provided: \n- Action items agreed: ",
            ],
            [
                'title' => 'Package Renewal Discussion',
                'category' => 'billing',
                'body' => "Package renewal discussion:\n- Current remaining lessons: \n- Proposed new package: \n- Payment terms agreed: \n- Target renewal deadline: ",
            ],
        ];

        foreach ($templates as $data) {
            StaffNoteTemplate::query()->firstOrCreate(
                ['title' => $data['title']],
                $data
            );
        }
    }
}
