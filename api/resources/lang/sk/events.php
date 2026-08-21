<?php

// Hlášky podujatí (App\Exceptions\DependenciesNotPublishedException a spol.).
return [
    'errors' => [
        'dependencies_not_published' => 'Podujatie sa nedá publikovať, kým nie je publikované aj :names.',
        'dependency_forbidden' => 'Na publikovanie :name nemáte právo.',
    ],
    'dependencies' => [
        'venue' => 'miesto :name',
        'canal' => 'kanál :name',
    ],
];
