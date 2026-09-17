<?php

return [
    'allowed_targets' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OBSERVATORY_ALLOWED_TARGETS', 'sita-docker')),
    ))),

    'github' => [
        'owner' => env('GITHUB_REPOSITORY_OWNER', 'msaririzki'),
        'repository' => env('GITHUB_REPOSITORY_NAME', 'sita'),
        'workflow' => env('GITHUB_EXPERIMENT_WORKFLOW', 'wif-poc.yml'),
        'token' => env('GITHUB_ACTIONS_TOKEN'),
        'app_id' => env('GITHUB_APP_ID'),
        'installation_id' => env('GITHUB_APP_INSTALLATION_ID'),
        'private_key_base64' => env('GITHUB_APP_PRIVATE_KEY_BASE64'),
    ],

    'evidence_oidc' => [
        'issuer' => env('EVIDENCE_OIDC_ISSUER', 'https://token.actions.githubusercontent.com'),
        'audience' => env('EVIDENCE_OIDC_AUDIENCE', rtrim((string) env('APP_URL'), '/').'/api/v1/evidence'),
        'discovery_url' => env('EVIDENCE_OIDC_DISCOVERY_URL', 'https://token.actions.githubusercontent.com/.well-known/openid-configuration'),
    ],

    'progress_oidc_audience' => env('PROGRESS_OIDC_AUDIENCE', rtrim((string) env('APP_URL'), '/').'/api/v1/progress'),

    'progress_stream_seconds' => (int) env('PROGRESS_STREAM_SECONDS', 55),
];
