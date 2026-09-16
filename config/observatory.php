<?php

return [
    'allowed_targets' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OBSERVATORY_ALLOWED_TARGETS', 'sita-docker')),
    ))),

    'github' => [
        'owner' => env('GITHUB_REPOSITORY_OWNER', 'msaririzki'),
        'repository' => env('GITHUB_REPOSITORY_NAME', 'sita'),
        'workflow' => env('GITHUB_EXPERIMENT_WORKFLOW', 'auth-experiment.yml'),
        'token' => env('GITHUB_ACTIONS_TOKEN'),
    ],
];
