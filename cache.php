<?php
/* Copyright Gergely Szerovay (gergely@szerovay.hu), Apache 2.0 or GPL 2+ license */

error_reporting(E_ALL);

$config = [];
require('config.php');

ini_set('session.use_cookies', '0');

if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
    $_SERVER['HTTP_ACCEPT_ENCODING'] = '';
}

/**
 * Class TStore
 */
class TStore {
    /**
     * @var TApplication
     */
    protected $app;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $type;

    public function __construct(TApplication $app, $type) {
        $this->app = $app;
        $this->config = $app->getConfig();
        $this->type = $type;
    }

    public function isCachedFileCountLimitExceeded(): bool {
        $fp = @fopen($this->config['fileCountLimit'][$this->type]['file'], 'cb+');

        if ($fp === false) {
            die('wp-static-proxy: Invalid log directory');
        }

        $cnt = (int)fread($fp, 10);
        if (empty($cnt)) {
            $cnt = 0;
        }
        fclose($fp);

        return ($cnt > $this->config['fileCountLimit'][$this->type]['limit']);
    }

    public function storeFile($filePath, $content) {
        $dir = dirname($filePath);
        $newDirCount = $this->getNewDirectoryCount($dir);
        $this->updateCachedFileCountLimit($newDirCount + 1);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->app->e500();
        }

        file_put_contents($filePath, $content);
    }

    public function getFile($filePath): ?string {
        if (!is_file($filePath)) {
            return null;
        }
        return file_get_contents($filePath);
    }

    public function getCachedFileCount() {
        if (is_file($this->config['fileCountLimit'][$this->type]['file'])) {
            return (int)file_get_contents($this->config['fileCountLimit'][$this->type]['file']);
        }
        return 0;
    }

    protected function updateCachedFileCountLimit($newFileCount) {
        $fp = @fopen($this->config['fileCountLimit'][$this->type]['file'], 'cb+');
        if ($fp === false) {
            die('wp-static-proxy: Invalid log directory');
        }

        if (flock($fp, LOCK_EX)) {
            $cnt = (int)fread($fp, 10);
            if (empty($cnt)) {
                $cnt = 0;
            }
//            if ($cnt > $this->config['fileCountLimit'][$type]['limit']) {
//                return false;
//            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $cnt + $newFileCount);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        else {
            return false;
        }
        fclose($fp);
        return true;
    }

    protected function getNewDirectoryCount($dir): int {
        $chunks = explode('/', $dir);

        $subDir = '';
        $newDirCount = 0;
        foreach ($chunks as $c) {
            $subDir .= $c . '/';

            if (!is_dir($subDir)) {
                $newDirCount++;
            }
        }
        return $newDirCount;
    }

    public function clearCache(string $path, string $extensionFilter = ''): int {
        $fileCount = $this->rmDirRecursive($path, $extensionFilter);
        $this->updateCachedFileCountLimit(-$fileCount);
        return $fileCount;
    }

    protected function rmDirRecursive(string $path, string $extensionFilter = ''): int {
        if (strpos($path, __DIR__ . DIRECTORY_SEPARATOR . 'cache-') !== 0) {
            die("Invalid directory: $path");
        }

        $fileCount = 0;

        if (trim(pathinfo($path, PATHINFO_BASENAME), '.') === '') {
            return 0;
        }

        if (is_dir($path)) {
            $files = glob($path . DIRECTORY_SEPARATOR . '{,.}*', GLOB_BRACE | GLOB_NOSORT);
            $fileCount += array_sum(array_map(array($this, 'rmDirRecursive'), $files, array_fill(0, count($files), $extensionFilter)));
            if ($extensionFilter == '') {
//                echo $path . "\n";
                @rmdir($path);
                $fileCount++;
            }
        }

        else {
            if ($extensionFilter != '') {
                $extensionFilterGZ = $extensionFilter.'.gz';
                $filterLength = strlen($extensionFilter);
                $filterLengthGZ = strlen($extensionFilterGZ);
                if ((substr(strtolower($path), -$filterLength, $filterLength) !== $extensionFilter) &&
                    (substr(strtolower($path), -$filterLengthGZ, $filterLengthGZ) !== $extensionFilterGZ)) {
                    return $fileCount;
                }
            }
//            echo $path . "\n";
            @unlink($path);
            $fileCount++;
        }
        return $fileCount;
    }
}

class THeaders {
//    protected function parseHeaders($rawHeaders) : void {
    /**
     * @var int
     */
    protected $status;
    /**
     * @var array
     */
    protected $headers;

    /**
     * @var string
     */
    protected $contentType;

    function __construct(array $rawHeaders) {
        $this->status = 0;
        $this->headers = [];
        foreach ($rawHeaders as $k => $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1])) {
                $this->headers[trim($t[0])] = trim($t[1]);
            }
            else {
                // "HTTP/1.1 200 OK"
                $s = preg_split('/\s+/', $v);
                $this->status = (int)$s[1];
            }
        }

        $this->contentType = '';
        if (isset($this->headers['Content-Type'])) {
            $this->contentType = trim(explode(';', $this->headers['Content-Type'])[0]); // eg. Content-Type: text/html; charset=utf-8
        }

    }

    public function outputHeaders(): void {
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
    }

    public function setHeader($k, $v): void {
        $this->headers[$k] = $v;
    }

    public function getHeader($k): string {
        return $this->headers[$k];
    }

    /**
     * @return int
     */
    public function getStatus(): int {
        return $this->status;
    }

    /**
     * @return array
     */
    public function getHeaders(): array {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getContentType(): string {
        return $this->contentType;
    }

}

class TImageOptimizer {
    public function optimizeJPEG($result): string {
        $imagick = new Imagick();
        $imagick->readImageBlob($result);
        $imagick->stripImage();
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(85);
        $imagick->setSamplingFactors(array('2x2', '1x1', '1x1'));
        $profiles = $imagick->getImageProfiles('icc', true);
        $imagick->stripImage();
        if (!empty($profiles)) {
            $imagick->profileImage('icc', $profiles['icc']);
        }
        $imagick->setInterlaceScheme(Imagick::INTERLACE_JPEG);
        $imagick->setColorspace(Imagick::COLORSPACE_SRGB);
        $result = $imagick->getImageBlob();
        $imagick->destroy();
        return $result;
    }

    public function optimizePNG($result): string {
        $imagick = new Imagick();
        $imagick->readImageBlob($result);
        $imagick->stripImage();
        $imagick->setImageFormat('png');
        $imagick->setOption('png:compression-level', 9);
        $result = $imagick->getImageBlob();
        $imagick->destroy();
        return $result;
    }

    public function optimizeGIF($result): string {
        $imagick = new Imagick();
        $imagick->readImageBlob($result);
        $imagick->stripImage();
        $imagick->setImageFormat('gif');
        $result = $imagick->getImageBlob();
        $imagick->destroy();
        return $result;
    }


}

class TApplication {
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $originalFilePath;

    /**
     * @var string
     */
    protected $sanitizedURLPath;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var THeaders
     */
    protected $headers;

    /**
     * @var string
     */
    protected $result;

    /**
     * @var string
     */
    protected $gzippedResult;

    /**
     * @var TStore
     */
    protected $store;

    /**
     * @var string
     */
    protected $processingFlags = '';

// (html|json|xml|css|js|svg|ico|ttf|otf|eot)
    protected $gzippableMimeTypes = [
        'text/html' => 'html',
        'text/plain' => 'html',
        'application/xhtml+xml' => 'html',

        'application/json' => 'json',
        'text/xml' => 'xml',

        'text/css' => 'css',

        'text/javascript' => 'js',
        'application/javascript' => 'js',
        'application/x-javascript' => 'js',

        'image/svg' => 'svg',
        'image/x-icon' => 'ico',

        'font/ttf' => 'ttf',
        'font/otf' => 'otf',
        'application/vnd.ms-fontobject' => 'eot'
    ];

    public function __construct($config) {
        if (empty($_REQUEST['a'])) {
            $this->e500();
        }

        $this->config = $config;

        switch ($_REQUEST['a']) {
            case 'request':
                $this->request();
                break;
            case 'htaccess':
                $this->generateHtaccess();
                break;
        }
        $this->e500();
    }

    protected function generateHtaccess() {
        $ht = file_get_contents('htaccess-template.txt');
        $from = [
            '[baseUrl]',
            '[adminKey]'
        ];

        $to = [
            $this->config['baseUrl'],
            $this->config['adminKey'],
        ];
        file_put_contents('.htaccess', str_replace($from, $to, $ht));
        die('.htaccess successfully generated.');
    }

    public function getConfig() {
        return $this->config;
    }

    public function e500(string $msg = ''): void {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        die($msg);
    }

    public function e404(string $msg = ''): void {
        $this->Log("request: (404 non-cached) $this->originalFilePath");
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
        die($msg);
    }

    protected function cacheAndResponse404(string $msg = ''): void {
        if ($this->config['store404s']) {
            $this->store = new TStore($this, '404');
            if ($this->store->isCachedFileCountLimitExceeded()) {
                $this->e404();
            }

            $this->processingFlags .= 'store404s ';

            $path = rtrim($this->sanitizedURLPath, '/');
            $this->store->storeFile(__DIR__ . DIRECTORY_SEPARATOR . 'cache-404' . DIRECTORY_SEPARATOR . $path . '.__404__', '');
        }

        $this->processingFlags = trim($this->processingFlags);
        $this->Log("request ($this->processingFlags): $this->originalFilePath");

        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
        die($msg);
    }

    protected function Log(string $msg): void {
        if (@file_put_contents($this->config['logFile'], date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX) === false) {
            die('wp-static-proxy: Invalid log directory');
        }
    }

    protected function sanitizePath(string $fn): string {
        $path = [];
        foreach (explode('/', $fn) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part !== '..') {
                $path[] = $part;
            }
            elseif (count($path) > 0) {
                array_pop($path);
            }
            else {
                $this->e500('Invalid path');
            }
        }

        $ret = implode('/', $path);
        if (substr($fn, -1) === '/') {
            $ret .= '/';
        }

        return $ret;
    }

    protected function fetchFromOrigin($url): void {
        $options = array(
            'http' => array(
                'method' => 'GET',
                'timeout' => $this->config['httpTimeout'],
                'follow_location' => 0,
            )
        );

        if (!empty($this->config['username'])) {
            $options['http']['header'] = 'Authorization: Basic ' . base64_encode("{$this->config['username']}:{$this->config['password']}");
        }

        $context = stream_context_create($options);
        $this->result = @file_get_contents($url, false, $context);

        $this->headers = new THeaders($http_response_header);
    }

    protected function textReplace($text, $fromToA) {
        $from = $to = [];
        foreach ($fromToA as $k => $v) {
            $from[] = $k;
            $to[] = $v;
        }
        return str_replace($from, $to, $text);
    }

    protected function cacheAndResponseText(): void {
        if (!empty($this->config['contentReplace'])) {
            $this->result = $this->textReplace($this->result, $this->config['contentReplace']);
        }

        $this->cacheAndResponse();
    }

    protected function cacheAndResponseJPEG(): void {
        if ($this->config['optimizeJPEG']) {
            $opt = new TImageOptimizer();
            $this->result = $opt->optimizeJPEG($this->result);
            $this->processingFlags .= 'optimizeJPEG ';
        }
        $this->cacheAndResponse();
    }

    protected function cacheAndResponsePNG(): void {
        if ($this->config['optimizePNG']) {
            $opt = new TImageOptimizer();
            $this->result = $opt->optimizePNG($this->result);
            $this->processingFlags .= 'optimizePNG ';
        }
        $this->cacheAndResponse();

    }

    protected function cacheAndResponseGIF(): void {
        if ($this->config['optimizeGIF']) {
            $opt = new TImageOptimizer();
            $this->result = $opt->optimizeGIF($this->result);
            $this->processingFlags .= 'optimizeGIF ';
        }
        $this->cacheAndResponse();
    }

    protected function cacheAndResponse() {

        if ($this->config['storeFiles']) {
            $this->processingFlags .= 'storeFiles ';

            $this->store->storeFile(__DIR__ . DIRECTORY_SEPARATOR . 'cache-content' . DIRECTORY_SEPARATOR . rtrim($this->sanitizedURLPath, '/'), $this->result);
        }

        $this->gzippedResult = false;
        if (isset($this->gzippableMimeTypes[$this->headers->getContentType()]) &&
            ($this->gzippableMimeTypes[$this->headers->getContentType()] === $this->extension)
        ) {

            $this->gzippedResult = gzencode($this->result, 9);
            if ($this->config['storeGZIPs']) {
                $this->processingFlags .= 'storeGZIPs ';
                $this->store->storeFile(__DIR__ . DIRECTORY_SEPARATOR . 'cache-content' . DIRECTORY_SEPARATOR . rtrim($this->sanitizedURLPath, '/') . '.gz', $this->gzippedResult);
            }
            if ($this->config['passthruGZIP']) {
                $this->processingFlags .= 'passthruGZIP ';
            }
        }

        $this->processingFlags = trim($this->processingFlags);
        $this->Log("request ($this->processingFlags): {$this->headers->getStatus()} {$this->headers->getContentType()} {$this->originalFilePath} => {$this->config['originUrl']} => " . $this->sanitizedURLPath);

        if (($this->gzippedResult !== false) && $this->config['passthruGZIP'] && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
            $this->headers->setHeader('Content-Encoding', 'gzip');
            $this->headers->setHeader('Vary', 'Accept-Encoding');
            $this->headers->setHeader('X-Precompressed', 'php');
            $this->headers->setHeader('Content-Length', strlen($this->gzippedResult));
            $this->headers->outputHeaders();
            die($this->gzippedResult);
        }

        $this->headers->setHeader('Content-Length', strlen($this->result));
        $this->headers->outputHeaders();
        die($this->result);
    }

    protected function cacheAndResponseRedirect() {
        $status = $this->headers->getStatus();
        $url = $this->textReplace($this->headers->getHeader('Location'), $this->config['redirectReplace']);

        if ($this->config['storeRedirects']) {
            $store = new TStore($this, 'redirect');

            $cached = $store->getFile(__DIR__ . DIRECTORY_SEPARATOR . 'cache-redirect' . DIRECTORY_SEPARATOR . rtrim($this->sanitizedURLPath, '/'));

            if ($cached !== null) {
                $cached = json_decode($cached, true);
                header("Location: " . $cached['url'], true, $cached['status']);
                exit;
            }

            if ($store->isCachedFileCountLimitExceeded()) {
                $this->e404();
            }

            $this->processingFlags .= 'storeRedirects ';

            $store->storeFile(__DIR__ . DIRECTORY_SEPARATOR . 'cache-redirect' . DIRECTORY_SEPARATOR . rtrim($this->sanitizedURLPath, '/'), json_encode(['status' => $status, 'url' => $url]));
        }

        $this->processingFlags = trim($this->processingFlags);
        $this->Log("request ($this->processingFlags): {$status} {$this->originalFilePath} => {$url}");
        header("Location: " . $url, true, $status);
        exit;
    }

    protected function admin($action) {
        $storeContent = new TStore($this, 'content');
        $storeRedirect = new TStore($this, 'redirect');
        $store404 = new TStore($this, '404');

        ini_set('session.use_cookies', '1');
        session_start();

        $this->Log("admin: $action");

        switch ($action) {
            case '':
                $view = 'login';
                break;
            case 'login_submit':
                if ($_REQUEST['password'] == $this->config['adminPassword']) {
                    $_SESSION['isLoggedIn'] = true;
                    header("Location: {$this->config['baseUrl']}/cache-admin/{$this->config['adminKey']}/dashboard", true, 301);
                    exit;
                }
                else {
                    die('Wrong password!');
                }
                break;
            case 'logout':
                $view = 'login';
                $_SESSION['isLoggedIn'] = false;
                break;
            case 'deleteHTMLCache':
                $fileCount = 0;
                $fileCount += $storeContent->clearCache(__DIR__ . DIRECTORY_SEPARATOR . 'cache-content', '.html');
                $fileCount += $storeRedirect->clearCache(__DIR__ . DIRECTORY_SEPARATOR . 'cache-redirect', '.html');
                $fileCount += $store404->clearCache(__DIR__ . DIRECTORY_SEPARATOR . 'cache-404', '.html');

                ?>
                <p><?= $fileCount ?> HTML files, cached redirects and 404 errors were deleted.</p>
                <?php
                $view = 'dashboard';
                break;
            case 'clearAllCache':
                $fileCount = 0;
                $fileCount += $storeContent->clearCache(__DIR__ . DIRECTORY_SEPARATOR . 'cache-content');
                $fileCount += $storeRedirect->clearCache(__DIR__ . DIRECTORY_SEPARATOR . 'cache-redirect');
                $fileCount += $store404->clearCache(__DIR__ . DIRECTORY_SEPARATOR . 'cache-404');

                ?>
                <p>The cache was cleared, <?= $fileCount ?> files were deleted.</p>
                <?php
                $view = 'dashboard';
                break;
            default:
                $view = $action;
        }

        switch ($view) {
            case 'login':
                ?>
                <form action="login_submit" method="post">
                    <div class="container">
                        <label for="password">Password: </label>
                        <input type="password" name="password" required>

                        <button type="submit">Login</button>
                    </div>
                </form>
                <?php
                break;
            case 'dashboard':
                ?>
                <p>Files in cache: <?= $storeContent->getCachedFileCount() ?>
                    / <?= $this->config['fileCountLimit']['content']['limit'] ?></p>
                <p>Redirects in cache: <?= $storeRedirect->getCachedFileCount() ?>
                    / <?= $this->config['fileCountLimit']['redirect']['limit'] ?></p>
                <p>Cached 404 errors: <?= $store404->getCachedFileCount() ?>
                    / <?= $this->config['fileCountLimit']['404']['limit'] ?></p>
                <p><a href="deleteHTMLCache">Delete HTML files, cached redirects and 404 errors</a></p>
                <p><a href="clearAllCache">Clear all cache</a></p>
                <p><a href="logout">Logout</a></p>
                <?php
                break;
            default:
                die ("Unknown view: " . $view);
        }
        exit;
    }

    protected function request(): void {
        $this->originalFilePath = $_REQUEST['f'];

        if (strpos($_REQUEST['f'], '//') !== false) {
            $this->e500();
        }
        $this->sanitizedURLPath = $this->sanitizePath($_REQUEST['f']);

//        $dir = __DIR__ . '/cache/' . dirname($this->sanitizedURLPath);

        $fDirectories = explode('/', $this->sanitizedURLPath);
//        print_r($fDirectories);
        if ($fDirectories[0] === 'cache-admin') {
            if ($fDirectories[1] === $this->config['adminKey']) {
                $this->admin($fDirectories[2]);
            }
            $this->e404();
        }

        $url = $this->config['originUrl'] . '/' . $this->sanitizedURLPath;
        $this->fetchFromOrigin($url);

        $status = $this->headers->getStatus();

        if ($this->result === FALSE) {
            if ($status === 404) {
                $this->cacheAndResponse404();
            }
            $this->e500();
        }

        if ($status === 403) {
            $this->cacheAndResponse404();
        }

        if ($status === 404) {
            $this->cacheAndResponse404();
        }

        if (($status === 301) || ($status === 301)) {
            $this->cacheAndResponseRedirect();
        }

        if ($status !== 200) {
            $this->Log("request (error): $status $this->originalFilePath => $url");
            http_response_code($status);
            exit;
        }

        // got 200

        $this->store = new TStore($this, 'content');
        if ($this->store->isCachedFileCountLimitExceeded()) {
            $this->e404();
        }

        if ($this->sanitizedURLPath === '') {
            $this->sanitizedURLPath = 'index.html';
        }

        $sanitizedFilePathX = explode('/', $this->sanitizedURLPath);
        if (strpos(end($sanitizedFilePathX), '.') === false) {
            // if no extension in url
            $this->sanitizedURLPath = rtrim($this->sanitizedURLPath, '/') . '.html';
        }

        $this->extension = strtolower(pathinfo($this->sanitizedURLPath, PATHINFO_EXTENSION));

        switch ($this->headers->getContentType()) {
            case 'text/html':
            case 'text/css':
            case 'text/xml':
                $this->cacheAndResponseText();
                break;
            case 'image/jpeg':
                $this->cacheAndResponseJPEG();
                break;
            case 'image/png':
                $this->cacheAndResponsePNG();
                break;
            case 'image/gif':
                $this->cacheAndResponseGIF();
                break;
            default:
                $this->cacheAndResponse();
                break;
        }
    }

    /**
     * @return THeaders
     */
    public function getHeaders(): THeaders {
        return $this->headers;
    }

}

new TApplication($config);
