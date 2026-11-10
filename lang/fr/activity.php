<?php

return [
    'request_unavailable' => 'La mesure d’activité est indisponible. Commencez une nouvelle observation.',
    'title' => 'Temps d’apprentissage actif estimé',
    'states' => [
        'disabled' => 'Collecte désactivée.',
        'unavailable' => 'Aucune mesure disponible pour cette période.',
        'eligible' => 'Mesure disponible. Aucune activité acceptée enregistrée.',
        'no_accepted' => 'Aucune activité acceptée enregistrée.',
        'partial' => 'Mesure partielle.',
    ],
    'explanation' => 'Estimations limitées aux textes et images des leçons, quiz, devoirs, exercices partagés et modification des notes de leçon pris en charge. Les vidéos, jeux, ressources externes et études hors plateforme ne sont pas mesurés.',
    'distinction' => 'Le temps est une estimation, pas une preuve d’attention, d’apprentissage ou d’achèvement. L’absence de mesure ne signifie pas l’absence d’apprentissage.',
    'contributors' => '{1} Un élève a contribué des intervalles acceptés. Cette sélection ne comprend pas toutes les personnes ayant étudié.|[2,*] :count élèves ont contribué des intervalles acceptés. Cette sélection ne comprend pas toutes les personnes ayant étudié.',
    'distribution' => 'Temps estimé par date',
    'overlap' => 'Contextes d’apprentissage simultanés',
    'interruptions' => 'Interruptions d’observation enregistrées',
    'calendar' => 'Temps estimé ; distinct des événements d’apprentissage enregistrés',
    'duration' => 'h:mm:ss',
    'empty' => 'Aucune mesure acceptée à répartir.',
];
