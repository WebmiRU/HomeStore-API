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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
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

            /*
             * Объекты публичные — иначе картинки не видно.
             *
             * По умолчанию Flysystem кладёт файлы приватными, а ссылки приложение
             * строит простые, без подписи: ImageResource отдаёт адрес вида
             * https://<bucket>/images/src/<sha>.jpg. Приватный объект по такой
             * ссылке отдаёт AccessDenied, и вместо картинки — заглушка.
             */
            'visibility' => 'public',

            /*
             * Пределы ожидания и версия TLS.
             *
             * connect_timeout здесь не для аккуратности, а потому что без него
             * страница с картинками не открывается вовсе. Промежуточное
             * оборудование в сети разработки иногда рвёт рукопожатие TLS: клиент
             * уходит в соединение и не получает ответа, а предел установления
             * соединения у cURL по умолчанию — 300 секунд. Запрос переживает
             * таймаут nginx и отдаёт 504, а клиентский <img> так и не получает
             * ни картинки, ни ошибки: крутится спиннер до перезагрузки. Пять
             * секунд — с запасом: столько нужно на TCP и TLS до хранилища.
             *
             * Своих попыток у SDK несколько, и с коротким пределом они как раз
             * и спасают: оборванное рукопожатие повторяется и почти всегда
             * проходит. Раньше повторялось, но каждая попытка ждала по пять
             * минут, и страница не открывалась ни разу.
             *
             * timeout — на всю операцию, а не на установление соединения.
             * Запас большой: миниатюра тянет оригинал целиком, и на медленной
             * связи это секунды, а не минуты.
             *
             * Ограничение TLS 1.2 — по требованию: в этой сети TLS 1.3
             * отваливается заметно чаще, и на странице с dozen картинок, где
             * соединений ровно столько же, разница видна невооружённым глазом.
             * Переменная окружения, а не флаг в коде: на машинах с нормальной
             * сетью ничего не меняется.
             */
            'options' => [
                'http' => [
                    'connect_timeout' => 5,
                    'timeout' => 30,
                    'curl' => env('AWS_S3_TLS_1_2_ONLY', false)
                        ? [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2]
                        : [],
                ],
            ],

            'throw' => false,
            'report' => false,
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
