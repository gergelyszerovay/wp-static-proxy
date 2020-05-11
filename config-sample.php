<?php

$cacheProtocol = 'https';
$cacheUrl = '//example.com';

$originProtocol = 'https';
$originUrl = '//backend.example.com';

$logPath = '../cache-log';

$config = [
    'adminKey' => '[your admin key, it can contain only letters and numbers]',
    'adminPassword' => '[your admin password]',

    'baseUrl' => $cacheProtocol . ':' . $cacheUrl, // no trailing slash !
    'originUrl' => $originProtocol . ':' . $originUrl, // no trailing slash !
    'httpTimeout' => 3,
    'logFile' => $logPath.'/store.log',
    'username' => '[username from .htpasswd]',
    'password' => '[password from .htpasswd]',
    'contentReplace' => array(
        'http:'.$originUrl => $cacheProtocol . ':' . $cacheUrl,
        'https:'.$originUrl => $cacheProtocol . ':' . $cacheUrl,
        $originUrl => $cacheProtocol . ':' . $cacheUrl,
    ),
    'redirectReplace' => array(
        'http:'.$originUrl => $cacheProtocol . ':' . $cacheUrl,
        'https:'.$originUrl => $cacheProtocol . ':' . $cacheUrl,
    ),
    'optimizeJPEG' => true, // enable / disable JPEG optimization
    'optimizeGIF' => true, // enable / disable GIF optimization
    'optimizePNG' => true, // enable / disable PNG optimization
    'storeGZIPs' => true, // enable / disable the cache of the .gz files
    'storeFiles' => true, // enable / disable the cache of files
    'store404s' => true, // enable / disable the cache 404 errors
    'storeRedirects' => true, // enable / disable the cache of the HTTP 301/302 redirects
    'passthruGZIP' => true, // when a file is not in the cache, serve the compressed version of the file to the client
    'fileCountLimit' => [ // limit the count of the cached items
        '404' => [
            'file' => $logPath.'/404.cnt',
            'limit' => 10000,
        ],
        'content' => [
            'file' => $logPath.'/content.cnt',
            'limit' => 10000,
        ],
        'redirect' => [
            'file' => $logPath.'/redirect.cnt',
            'limit' => 10000,
        ]

    ]

];
