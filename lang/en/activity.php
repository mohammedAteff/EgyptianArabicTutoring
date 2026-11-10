<?php

return [
    'request_unavailable' => 'Activity measurement unavailable. Start a new observation.',
    'title' => 'Estimated Active Learning Time',
    'states' => [
        'disabled' => 'Collection disabled.',
        'unavailable' => 'No measurement available for this period.',
        'eligible' => 'Measurement available. No accepted activity recorded.',
        'no_accepted' => 'No accepted activity recorded.',
        'partial' => 'Partial measurement.',
    ],
    'explanation' => 'Bounded estimates from supported lesson text and images, quizzes, assignments, shared homework and lesson note editing. Video, games, external resources and off-platform study are not measured.',
    'distinction' => 'Time is an estimate, not proof of attention, learning or completion. No measurement does not mean no learning.',
    'contributors' => '{1} One student contributed accepted intervals. This selection does not include everyone who studied.|[2,*] :count students contributed accepted intervals. This selection does not include everyone who studied.',
    'distribution' => 'Estimated time by date',
    'overlap' => 'Overlapping learning contexts',
    'interruptions' => 'Recorded observation interruptions',
    'calendar' => 'Estimated time; separate from recorded learning events',
    'duration' => 'h:mm:ss',
    'empty' => 'No accepted measurement to distribute.',
];
