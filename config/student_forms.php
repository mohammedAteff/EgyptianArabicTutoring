<?php

return [
    'short-form' => [
        'internal_name' => 'Initial Student Intake',
        'metadata' => [
            'slug' => 'short-form',
            'title' => 'A little about your Arabic journey',
            'description' => "Before we meet, I'd love to understand where you're starting and what you'd like to achieve.\n\nJust four quick questions—there are no wrong answers.",
            'is_mandatory' => true,
            'can_edit_after_submission' => false,
            'triggers' => [
                'pre_booking',
            ],
        ],
        'questions' => [
            [
                'question_key' => 'awa_int_01',
                'label' => 'Which best describes your experience with Arabic so far?',
                'description' => 'Choose whichever feels closest. We\'ll explore your level together.',
                'question_type' => 'single_choice',
                'is_required' => true,
                'assistant_visible' => true,
                'sort_order' => 0,
                'validation_rules' => null,
                'presentation_config' => [
                    'language' => 'en',
                    'form_title' => 'A little about your Arabic journey',
                    'form_description' => "Before we meet, I'd love to understand where you're starting and what you'd like to achieve.\n\nJust four quick questions—there are no wrong answers.",
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'I\'m completely new to Arabic.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'I\'ve had a little exposure, but haven\'t studied it much.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'I\'ve studied or practiced Arabic before.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'I\'ve studied or used Arabic quite extensively.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'I\'m not sure how to describe my experience.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_int_02',
                'label' => 'Which kind of Arabic would you like to focus on?',
                'description' => 'It\'s perfectly fine if you haven\'t decided yet.',
                'question_type' => 'single_choice',
                'is_required' => true,
                'assistant_visible' => true,
                'sort_order' => 1,
                'validation_rules' => null,
                'presentation_config' => [
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'Egyptian Arabic — everyday spoken language.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Modern Standard Arabic — formal, written and literary Arabic.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'I\'d like to learn both.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'I\'m not sure yet. I\'d appreciate guidance.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_int_03',
                'label' => 'What would you most like to achieve with Arabic?',
                'description' => 'Choose your most important priority for now.',
                'question_type' => 'single_choice',
                'is_required' => true,
                'assistant_visible' => true,
                'sort_order' => 2,
                'validation_rules' => null,
                'presentation_config' => [
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'Speak more comfortably and naturally.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Understand spoken Arabic better.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Read Arabic texts.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Express myself in written Arabic.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'Prepare for a particular real-life, work or study goal.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'Explore the language and its cultures.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                    [
                        'label' => 'I\'m still discovering my goals.',
                        'value' => 'option_07',
                        'sort_order' => 6,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_int_04',
                'label' => 'Where do you most hope to use Arabic?',
                'description' => 'Think about the situations or people that matter most to you.',
                'question_type' => 'single_choice',
                'is_required' => true,
                'assistant_visible' => true,
                'sort_order' => 3,
                'validation_rules' => null,
                'presentation_config' => [
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'With friends, relatives or people close to me.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'In everyday conversations and daily life.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'While traveling or spending time in an Arabic-speaking country.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'At work or in my studies.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'With books, films, media or other cultural content.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'For another reason or personal interest.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_07',
                        'sort_order' => 6,
                    ],
                ],
            ],
        ],
    ],
    'long-form' => [
        'internal_name' => 'Student Learning & Personalization Profile',
        'metadata' => [
            'slug' => 'long-form',
            'title' => 'Your Learning Profile',
            'description' => "Everyone brings different experiences, interests, and ambitions to learning Arabic.\n\nI'd love to know a little more about you—not only your goals with Arabic, but also the things you enjoy, the experiences you've had, and the subjects that matter to you.\n\nYour answers can help me prepare lessons, conversations, exercises, and learning materials that feel genuinely relevant to you.\n\nEverything here is optional. You can answer as much or as little as you like, save your progress, and return whenever you wish.\n\nThere's no need for long answers. A few words are often plenty.",
            'is_mandatory' => false,
            'can_edit_after_submission' => false,
            'triggers' => [
                'after_booking',
            ],
        ],
        'questions' => [
            [
                'question_key' => 'awa_pro_01',
                'label' => 'Which part of Arabic would you most like some extra help with right now?',
                'description' => 'Choose whatever feels closest. It\'s fine if you\'re not sure yet.',
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 0,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_1',
                        'title' => 'Your Arabic journey',
                        'description' => 'Let\'s explore where you\'d like some support and what meaningful progress could look like for you.',
                    ],
                    'language' => 'en',
                    'form_title' => 'Your Learning Profile',
                    'form_description' => "Everyone brings different experiences, interests, and ambitions to learning Arabic.\n\nI'd love to know a little more about you—not only your goals with Arabic, but also the things you enjoy, the experiences you've had, and the subjects that matter to you.\n\nYour answers can help me prepare lessons, conversations, exercises, and learning materials that feel genuinely relevant to you.\n\nEverything here is optional. You can answer as much or as little as you like, save your progress, and return whenever you wish.\n\nThere's no need for long answers. A few words are often plenty.",
                    'introduction_heading' => 'A learning experience made for you',
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'Speaking and finding the right words.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Understanding spoken Arabic.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Reading Arabic.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Writing Arabic.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'Pronunciation.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'Grammar and sentence building.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                    [
                        'label' => 'I\'m new to Arabic, so I\'m not sure yet.',
                        'value' => 'option_07',
                        'sort_order' => 6,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_x01',
                'label' => 'What feels most difficult about reading Arabic at the moment?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 1,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_1',
                        'title' => 'Your Arabic journey',
                        'description' => 'Let\'s explore where you\'d like some support and what meaningful progress could look like for you.',
                    ],
                ],
                'conditional_logic' => [
                    'logic_version' => 1,
                    'mode' => 'any',
                    'conditions' => [
                        [
                            'question_key' => 'awa_pro_01',
                            'operator' => 'equals',
                            'value' => 'option_03',
                        ],
                    ],
                ],
                'options' => [
                    [
                        'label' => 'Recognizing Arabic letters.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Reading words accurately.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Reading without vowel marks.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Reading fluently.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'Understanding unfamiliar vocabulary.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_x02',
                'label' => 'What makes spoken Arabic hardest to follow?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 2,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_1',
                        'title' => 'Your Arabic journey',
                        'description' => 'Let\'s explore where you\'d like some support and what meaningful progress could look like for you.',
                    ],
                ],
                'conditional_logic' => [
                    'logic_version' => 1,
                    'mode' => 'any',
                    'conditions' => [
                        [
                            'question_key' => 'awa_pro_01',
                            'operator' => 'equals',
                            'value' => 'option_02',
                        ],
                    ],
                ],
                'options' => [
                    [
                        'label' => 'People speaking too quickly.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Unfamiliar words.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Accent or pronunciation differences.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Keeping up with longer conversations.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_02',
                'label' => 'What\'s one small achievement in Arabic that would feel especially meaningful to you?',
                'description' => 'It could be having a relaxed conversation, understanding a scene from a film, reading a short passage, or something entirely different. A few words are enough.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 3,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_1',
                        'title' => 'Your Arabic journey',
                        'description' => 'Let\'s explore where you\'d like some support and what meaningful progress could look like for you.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_03',
                'label' => 'When you\'re speaking Arabic, how would you prefer me to correct mistakes?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 4,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_2',
                        'title' => 'How we\'ll learn together',
                        'description' => 'A few preferences can help me make our lessons comfortable, encouraging, and enjoyable.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'Correct me as soon as you notice a mistake.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Let me finish speaking, then go over my mistakes.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Correct important mistakes immediately and save smaller ones for later.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'I\'m not sure yet—we can figure it out together.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_04',
                'label' => 'What would help you feel most comfortable trying things out during a lesson?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 5,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_2',
                        'title' => 'How we\'ll learn together',
                        'description' => 'A few preferences can help me make our lessons comfortable, encouraging, and enjoyable.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'Having time to think before answering.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Seeing an example before trying something myself.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Starting with smaller, guided activities.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Being able to experiment without frequent interruption.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'I generally feel comfortable trying new things.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_05',
                'label' => 'Which kind of activity would you most enjoy starting with?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 6,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_2',
                        'title' => 'How we\'ll learn together',
                        'description' => 'A few preferences can help me make our lessons comfortable, encouraging, and enjoyable.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'Guided conversations.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Realistic role-plays and dialogues.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Listening and discussing what I hear.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Reading and exploring texts.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'Writing short messages or passages.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'Word games, puzzles or creative challenges.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                    [
                        'label' => 'I\'d enjoy a mixture of activities.',
                        'value' => 'option_07',
                        'sort_order' => 6,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_08',
                        'sort_order' => 7,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_06',
                'label' => 'What kinds of work, study, or professional roles have been part of your life?',
                'description' => 'You can mention your current work, a previous profession, something you\'ve studied, volunteering, or a field you\'ve spent time in. Whatever you\'d like to share is enough—there\'s no need for personal or employer details.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 7,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_3',
                        'title' => 'Your background and experiences',
                        'description' => null,
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_07',
                'label' => 'Is there another part of your background or life experience you\'d enjoy sharing?',
                'description' => 'Perhaps a personal project, a place you\'ve spent time, volunteering, a creative pursuit, or something you\'ve accomplished. Only share what feels comfortable and relevant to you.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 8,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_3',
                        'title' => 'Your background and experiences',
                        'description' => null,
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_08',
                'label' => 'What are some things you genuinely enjoy outside learning Arabic?',
                'description' => 'Think of hobbies, activities, favorite subjects, books, films, games, creative interests, or anything you love spending time on. A few examples are plenty.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 9,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_4',
                        'title' => 'Your interests and everyday life',
                        'description' => 'The things you enjoy can become wonderful starting points for conversations, examples, and creative learning materials.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_09',
                'label' => 'Which part of Egyptian or other Arabic-speaking cultures are you most curious about right now?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 10,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_4',
                        'title' => 'Your interests and everyday life',
                        'description' => 'The things you enjoy can become wonderful starting points for conversations, examples, and creative learning materials.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'Everyday language, customs and expressions.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Films and television.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Music and podcasts.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Stories and literature.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'Food and cooking.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'History, art and architecture.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                    [
                        'label' => 'Another area we can discuss together.',
                        'value' => 'option_07',
                        'sort_order' => 6,
                    ],
                    [
                        'label' => 'I don\'t have a particular preference yet.',
                        'value' => 'option_08',
                        'sort_order' => 7,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_x03',
                'label' => 'Are there any particular films, shows, books, songs, or creators you\'d love to explore through Arabic?',
                'description' => 'Just a few examples are enough. It\'s completely fine to leave this blank.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 11,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_4',
                        'title' => 'Your interests and everyday life',
                        'description' => 'The things you enjoy can become wonderful starting points for conversations, examples, and creative learning materials.',
                    ],
                ],
                'conditional_logic' => [
                    'logic_version' => 1,
                    'mode' => 'any',
                    'conditions' => [
                        [
                            'question_key' => 'awa_pro_09',
                            'operator' => 'equals',
                            'value' => 'option_02',
                        ],
                        [
                            'question_key' => 'awa_pro_09',
                            'operator' => 'equals',
                            'value' => 'option_03',
                        ],
                        [
                            'question_key' => 'awa_pro_09',
                            'operator' => 'equals',
                            'value' => 'option_04',
                        ],
                    ],
                ],
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_10',
                'label' => 'Outside our lessons, how much time would realistically feel comfortable for Arabic practice?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 12,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_4',
                        'title' => 'Your interests and everyday life',
                        'description' => 'The things you enjoy can become wonderful starting points for conversations, examples, and creative learning materials.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'I\'d prefer to practice mainly during lessons.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'A few minutes once or twice a week.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Around 5–10 minutes on most days.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'Around 15–30 minutes on most days.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'Longer practice sessions on a few days each week.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'It varies too much to say.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_07',
                        'sort_order' => 6,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_11',
                'label' => 'Where do you currently encounter Arabic most often outside lessons?',
                'description' => null,
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 13,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_4',
                        'title' => 'Your interests and everyday life',
                        'description' => 'The things you enjoy can become wonderful starting points for conversations, examples, and creative learning materials.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'In conversations with people around me.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'Through messages or social media.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Through films, videos, music or podcasts.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'In books, articles or other reading.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'At work or in my studies.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'I rarely encounter Arabic yet.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                    [
                        'label' => 'Somewhere else or I\'m not sure.',
                        'value' => 'option_07',
                        'sort_order' => 6,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_12',
                'label' => 'Which parts of your world would you enjoy bringing into your Arabic lessons or practice activities?',
                'description' => 'This might mean conversations, vocabulary, stories, or exercises inspired by your interests or experience. You can also keep lessons separate from those parts of your life.',
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 14,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_5',
                        'title' => 'Making learning materials your own',
                        'description' => 'Sometimes the best learning materials are inspired by the things that matter to you. I\'d love to know whether you\'d enjoy bringing some of your world into your Arabic lessons.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'My hobbies and personal interests.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'My work, studies, or previous professional experience.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'A mixture of my interests and professional background.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'I\'d prefer to explore different subjects unrelated to my own background.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'I\'d rather decide together from time to time.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_06',
                        'sort_order' => 5,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_x04',
                'label' => 'Which parts of your work, studies, or past experience would you enjoy exploring through Arabic?',
                'description' => 'For example, situations from your profession, subjects you\'ve studied, useful vocabulary, or experiences you\'d enjoy turning into practice activities. You can also mention anything you\'d prefer to leave out.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 15,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_5',
                        'title' => 'Making learning materials your own',
                        'description' => 'Sometimes the best learning materials are inspired by the things that matter to you. I\'d love to know whether you\'d enjoy bringing some of your world into your Arabic lessons.',
                    ],
                ],
                'conditional_logic' => [
                    'logic_version' => 1,
                    'mode' => 'any',
                    'conditions' => [
                        [
                            'question_key' => 'awa_pro_12',
                            'operator' => 'equals',
                            'value' => 'option_02',
                        ],
                        [
                            'question_key' => 'awa_pro_12',
                            'operator' => 'equals',
                            'value' => 'option_03',
                        ],
                    ],
                ],
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_x05',
                'label' => 'Is there a particular hobby, interest, story, or creative project you\'d love to see turned into an Arabic activity?',
                'description' => 'It could inspire a conversation, a short story, vocabulary practice, a game, or another activity. A short answer is perfect.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 16,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_5',
                        'title' => 'Making learning materials your own',
                        'description' => 'Sometimes the best learning materials are inspired by the things that matter to you. I\'d love to know whether you\'d enjoy bringing some of your world into your Arabic lessons.',
                    ],
                ],
                'conditional_logic' => [
                    'logic_version' => 1,
                    'mode' => 'any',
                    'conditions' => [
                        [
                            'question_key' => 'awa_pro_12',
                            'operator' => 'equals',
                            'value' => 'option_01',
                        ],
                        [
                            'question_key' => 'awa_pro_12',
                            'operator' => 'equals',
                            'value' => 'option_03',
                        ],
                    ],
                ],
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_13',
                'label' => 'How would you feel about personal visual touches in your learning materials?',
                'description' => 'For example, subtle colors, illustrations, or themed details in your personal worksheets. The materials will always remain clear and easy to read.',
                'question_type' => 'single_choice',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 17,
                'validation_rules' => null,
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_5',
                        'title' => 'Making learning materials your own',
                        'description' => 'Sometimes the best learning materials are inspired by the things that matter to you. I\'d love to know whether you\'d enjoy bringing some of your world into your Arabic lessons.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                    [
                        'label' => 'I\'d prefer a clean, consistent style without personal themes.',
                        'value' => 'option_01',
                        'sort_order' => 0,
                    ],
                    [
                        'label' => 'I\'d enjoy occasional personalized colors or small visual accents.',
                        'value' => 'option_02',
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'I\'d enjoy illustrations or visual themes inspired by my interests or background.',
                        'value' => 'option_03',
                        'sort_order' => 2,
                    ],
                    [
                        'label' => 'I\'m happy with whatever you think works best.',
                        'value' => 'option_04',
                        'sort_order' => 3,
                    ],
                    [
                        'label' => 'I\'m not sure yet.',
                        'value' => 'option_05',
                        'sort_order' => 4,
                    ],
                ],
            ],
            [
                'question_key' => 'awa_pro_x06',
                'label' => 'Are there any colors, objects, symbols, illustration styles, or themes you\'d especially enjoy seeing in your personal learning materials?',
                'description' => 'You might think of favorite colors, elements connected to your work or hobbies, objects you enjoy, or a visual style you like. Feel free to mention anything you\'d prefer to avoid, too.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 18,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_5',
                        'title' => 'Making learning materials your own',
                        'description' => 'Sometimes the best learning materials are inspired by the things that matter to you. I\'d love to know whether you\'d enjoy bringing some of your world into your Arabic lessons.',
                    ],
                ],
                'conditional_logic' => [
                    'logic_version' => 1,
                    'mode' => 'any',
                    'conditions' => [
                        [
                            'question_key' => 'awa_pro_13',
                            'operator' => 'equals',
                            'value' => 'option_02',
                        ],
                        [
                            'question_key' => 'awa_pro_13',
                            'operator' => 'equals',
                            'value' => 'option_03',
                        ],
                    ],
                ],
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_14',
                'label' => 'Are there any subjects or themes you\'d rather not have included in our lesson conversations or learning materials?',
                'description' => 'Completely optional. You don\'t need to explain why, and you can always change your preferences.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 19,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_6',
                        'title' => 'Your comfort and boundaries',
                        'description' => 'These last questions are simply an opportunity to share anything that might make our lessons more comfortable or better suited to you.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                ],
            ],
            [
                'question_key' => 'awa_pro_15',
                'label' => 'Is there anything I could adjust about lesson pace, presentation, or learning materials to make them easier for you to use?',
                'description' => 'For example, more thinking time, larger text, different spacing, or clearer visual organization. No personal or medical explanation is needed.',
                'question_type' => 'long_text',
                'is_required' => false,
                'assistant_visible' => false,
                'sort_order' => 20,
                'validation_rules' => [
                    'max_length' => 10000,
                ],
                'presentation_config' => [
                    'section' => [
                        'key' => 'section_6',
                        'title' => 'Your comfort and boundaries',
                        'description' => 'These last questions are simply an opportunity to share anything that might make our lessons more comfortable or better suited to you.',
                    ],
                ],
                'conditional_logic' => null,
                'options' => [
                ],
            ],
        ],
    ],
];
