<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Job Source API Credentials
    |--------------------------------------------------------------------------
    */

    'adzuna' => [
        'app_id' => env('ADZUNA_APP_ID'),
        'api_key' => env('ADZUNA_API_KEY'),
        'country' => env('ADZUNA_COUNTRY', 'us'),
        'base_url' => 'https://api.adzuna.com/v1/api',
    ],

    'jsearch' => [
        'api_key' => env('JSEARCH_API_KEY'),
        'base_url' => 'https://jsearch.p.rapidapi.com',
    ],

    'muse' => [
        'base_url' => 'https://www.themuse.com/api/public',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingestion Settings
    |--------------------------------------------------------------------------
    */

    'ingestion' => [
        'batch_size' => 50,
        'stale_after_days' => 30,
        'classification_model' => env('JOB_CLASSIFICATION_MODEL', 'claude-haiku-4-5-20251001'),
    ],

];
