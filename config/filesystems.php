<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         * Cloudflare R2, used only by `backup:database` to ship nightly Postgres
         * dumps off the Linode box. Nothing user-facing is ever written here.
         *
         * The bucket is created with EU jurisdiction, which is what keeps the
         * objects physically in the EU and makes the transfer defensible under
         * GDPR without client-side encryption on top of R2's own at-rest AES-256.
         * That jurisdiction is baked into the endpoint hostname: an EU bucket
         * answers on <account>.eu.r2.cloudflarestorage.com and returns 403 on the
         * plain <account>.r2.cloudflarestorage.com host. Getting this wrong looks
         * exactly like a bad credential, so R2_ENDPOINT is derivable but
         * overridable.
         */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            // R2 has no regions; the S3 SDK still demands the key be present.
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT') ?: sprintf(
                'https://%s.%s.r2.cloudflarestorage.com',
                env('R2_ACCOUNT_ID'),
                env('R2_JURISDICTION', 'eu'),
            ),
            'use_path_style_endpoint' => true,
            // Unlike the user-facing disks, a silent false return here would let
            // a failed upload be reported as a successful backup. Let it throw so
            // the command's catch block can shout about it.
            'throw' => true,
            'report' => false,
            /*
             * aws-sdk-php >= 3.337 defaults both of these to `when_supported`,
             * which adds CRC32 integrity trailers to every PutObject. R2's S3
             * compatibility layer has been inconsistent about accepting them and
             * rejects with an opaque error when it doesn't. `when_required` gets
             * the pre-3.337 behaviour; TLS plus the explicit size check in
             * BackupDatabase::upload() already cover corruption in transit.
             */
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
