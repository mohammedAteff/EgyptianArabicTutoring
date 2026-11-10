<?php

namespace App\Domains\Students\Services;

class StudentHomeNextAction
{
    /** @param array<string,array{title:string,detail:string,button:string,url:string,source?:string}|null> $candidates
     * @return array{title:string,detail:string,button:string,url:string,source?:string,kind:string}
     */
    public function choose(array $candidates): array
    {
        foreach (['join', 'soon', 'due_homework', 'mandatory_form', 'homework', 'expiring', 'continuation', 'learning', 'book', 'later', 'resource', 'diagnostic', 'feedback', 'fallback'] as $kind) {
            if (isset($candidates[$kind])) {
                return $candidates[$kind] + ['kind' => $kind];
            }
        }

        throw new \LogicException('Student Home requires its safe learning fallback.');
    }
}
