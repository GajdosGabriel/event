<?php

// Konzolové nástroje v admine (App\Http\Controllers\Admin\AdminToolsController).
// Beh drží ToolRunTracker v cache, takže po expirácii sa už nenájde.
return [
    'errors' => [
        'run_not_found' => 'Beh sa nenašiel alebo vypršal.',
    ],
];
