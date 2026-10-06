<?php

declare(strict_types=1);

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('PORTFOLIO_CORS_ORIGINS') ?: 'http://localhost:5173,http://127.0.0.1:5173')));

if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('PORTFOLIO_DB_HOST') ?: '127.0.0.1';
    $database = getenv('PORTFOLIO_DB_NAME') ?: 'portfolio_db';
    $username = getenv('PORTFOLIO_DB_USER') ?: 'root';
    $password = getenv('PORTFOLIO_DB_PASSWORD') ?: '';

    $connection = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database),
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}

function jsonResponse(bool $success, string $message, mixed $data = [], array $errors = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => (object)$errors,
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function requireAdmin(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        jsonResponse(false, 'Unauthorized.', [], ['auth' => ['Admin authentication required.']], 401);
    }
}

function readRequestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Request body must be a valid JSON object.', [], ['body' => ['Invalid JSON.']], 400);
    }

    return $decoded;
}

function validateText(array $input, string $field, string $label, int $maximum, bool $required, array &$errors): ?string
{
    $rawValue = $input[$field] ?? '';
    if (!is_string($rawValue) && !is_numeric($rawValue)) {
        $errors[$field] = [$label . ' must be text.'];
        return null;
    }
    $value = trim((string)$rawValue);

    if ($required && $value === '') {
        $errors[$field] = [$label . ' is required.'];
        return null;
    }
    if (mb_strlen($value) > $maximum) {
        $errors[$field] = [$label . ' must not exceed ' . $maximum . ' characters.'];
        return null;
    }

    return $value;
}

function validateUrl(array $input, string $field, string $label, bool $required, array &$errors): ?string
{
    $rawValue = $input[$field] ?? '';
    if (!is_string($rawValue)) {
        $errors[$field] = [$label . ' must be a URL string.'];
        return null;
    }
    $value = trim($rawValue);
    if ($value === '' && !$required) {
        return null;
    }
    if ($value === '' || mb_strlen($value) > 2000 || filter_var($value, FILTER_VALIDATE_URL) === false) {
        $errors[$field] = [$label . ' must be a valid URL.'];
        return null;
    }

    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        $errors[$field] = [$label . ' must use HTTP or HTTPS.'];
        return null;
    }

    return $value;
}

function mediaUploadRules(string $kind): array
{
    if ($kind === 'videos') {
        return [
            'directory' => 'videos',
            'field' => 'video',
            'max_bytes' => 100 * 1024 * 1024,
            'types' => [
                'mp4' => ['video/mp4'],
                'webm' => ['video/webm'],
                'mov' => ['video/quicktime', 'video/mp4'],
            ],
            'prefix' => 'video',
        ];
    }

    return [
        'directory' => 'services',
        'field' => 'image',
        'max_bytes' => 10 * 1024 * 1024,
        'types' => [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ],
        'prefix' => 'service',
    ];
}

function inspectMediaUpload(string $kind, array &$errors): ?array
{
    $rules = mediaUploadRules($kind);
    $file = $_FILES[$rules['field']] ?? null;
    if ($file === null) {
        return null;
    }
    if (!is_array($file) || is_array($file['tmp_name'] ?? null) || is_array($file['name'] ?? null)) {
        $errors[$rules['field']] = ['Upload one file at a time.'];
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[$rules['field']] = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ['The uploaded file exceeds the server upload limit.'],
            UPLOAD_ERR_PARTIAL => ['The file upload was incomplete. Please try again.'],
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => ['The server could not store the uploaded file.'],
            UPLOAD_ERR_EXTENSION => ['The server rejected this file upload.'],
            default => ['The file could not be uploaded.'],
        };
        return null;
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        $errors[$rules['field']] = ['The uploaded file is invalid.'];
        return null;
    }

    $fileSize = $file['size'] ?? null;
    if (!is_int($fileSize) || $fileSize < 1 || $fileSize > $rules['max_bytes']) {
        $errors[$rules['field']] = ['The file is empty or exceeds the allowed size limit.'];
        return null;
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!isset($rules['types'][$extension])) {
        $errors[$rules['field']] = ['This file type is not supported.'];
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryPath);
    if (!is_string($mimeType) || !in_array($mimeType, $rules['types'][$extension], true)) {
        $errors[$rules['field']] = ['The file content does not match an allowed file type.'];
        return null;
    }

    try {
        $filename = $rules['prefix'] . '-' . bin2hex(random_bytes(16)) . '.' . $extension;
    } catch (Throwable) {
        $errors[$rules['field']] = ['A safe filename could not be generated.'];
        return null;
    }

    return [
        'temporary_path' => $temporaryPath,
        'directory' => $rules['directory'],
        'filename' => $filename,
        'relative_path' => 'uploads/' . $rules['directory'] . '/' . $filename,
    ];
}

function storeMediaUpload(array $upload): string
{
    $directory = dirname(__DIR__) . '/uploads/' . $upload['directory'];
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The upload directory could not be created.');
    }

    $destination = $directory . '/' . $upload['filename'];
    if (!move_uploaded_file($upload['temporary_path'], $destination)) {
        throw new RuntimeException('The uploaded file could not be stored.');
    }

    return $upload['relative_path'];
}

function removeStoredMediaFile(string $relativePath): void
{
    if (!preg_match('#^uploads/(videos|services)/(video|service)-[a-f0-9]{32}\.(mp4|webm|mov|jpg|jpeg|png|webp)$#', $relativePath, $matches)
        || ($matches[1] === 'videos' && $matches[2] !== 'video')
        || ($matches[1] === 'services' && $matches[2] !== 'service')) {
        return;
    }

    $absolutePath = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

function publicMediaPath(mixed $storedPath, string $kind): mixed
{
    if (!is_string($storedPath)) {
        return $storedPath;
    }

    $rules = mediaUploadRules($kind);
    $pattern = '#^uploads/' . preg_quote($rules['directory'], '#') . '/([a-z]+-[a-f0-9]{32}\.[a-z0-9]+)$#';
    if (preg_match($pattern, $storedPath, $matches) !== 1) {
        return $storedPath;
    }

    return '/api/media/' . $rules['directory'] . '?file=' . rawurlencode($matches[1]);
}

function serveUploadedMedia(array $segments, string $method): never
{
    if (!in_array($method, ['GET', 'HEAD'], true) || count($segments) !== 3) {
        jsonResponse(false, 'Media resource not found.', [], [], 404);
    }

    $directory = $segments[1];
    $filename = $segments[2];
    if (!in_array($directory, ['videos', 'services'], true)
        || !preg_match('/^(video|service)-[a-f0-9]{32}\.(mp4|webm|mov|jpg|jpeg|png|webp)$/', $filename, $matches)
        || ($directory === 'videos' && $matches[1] !== 'video')
        || ($directory === 'services' && $matches[1] !== 'service')) {
        jsonResponse(false, 'Media resource not found.', [], [], 404);
    }

    $rules = mediaUploadRules($directory);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!isset($rules['types'][$extension])) {
        jsonResponse(false, 'Media resource not found.', [], [], 404);
    }

    $root = realpath(dirname(__DIR__) . '/uploads/' . $directory);
    $filePath = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . $filename);
    if ($root === false || $filePath === false || !str_starts_with($filePath, $root . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
        jsonResponse(false, 'Media resource not found.', [], [], 404);
    }

    $mimeType = $rules['types'][$extension][0];
    $fileSize = filesize($filePath);
    if ($fileSize === false) {
        jsonResponse(false, 'Media resource could not be read.', [], [], 500);
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');

    $start = 0;
    $end = $fileSize - 1;
    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
    if ($rangeHeader !== '') {
        if (preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $matches) !== 1) {
            header('Content-Range: bytes */' . $fileSize);
            http_response_code(416);
            exit;
        }
        if ($matches[1] === '' && $matches[2] === '') {
            header('Content-Range: bytes */' . $fileSize);
            http_response_code(416);
            exit;
        }
        if ($matches[1] === '') {
            $suffixLength = (int)$matches[2];
            $start = max(0, $fileSize - $suffixLength);
        } else {
            $start = (int)$matches[1];
            if ($matches[2] !== '') {
                $end = min((int)$matches[2], $end);
            }
        }
        if ($start > $end || $start >= $fileSize) {
            header('Content-Range: bytes */' . $fileSize);
            http_response_code(416);
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$fileSize");
    }

    $length = $end - $start + 1;
    header('Content-Length: ' . $length);
    if ($method === 'HEAD') {
        exit;
    }

    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        http_response_code(500);
        exit;
    }
    fseek($handle, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($handle);
    exit;
}

function phpIniBytes(string $setting): int
{
    $value = trim((string)ini_get($setting));
    if ($value === '' || $value === '0') {
        return 0;
    }
    if (preg_match('/^(\d+)\s*([KMG]?)$/i', $value, $matches) !== 1) {
        return 0;
    }
    $multipliers = ['' => 1, 'K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3];
    return (int)$matches[1] * $multipliers[strtoupper($matches[2])];
}

function validateOrder(array $input, array &$errors): int
{
    $value = $input['display_order'] ?? 1;
    $validated = filter_var($value, FILTER_VALIDATE_INT);
    if ($validated === false || $validated < 0) {
        $errors['display_order'] = ['Display order must be a non-negative integer.'];
        return 0;
    }
    return $validated;
}

function validateBoolean(array $input, string $field, array &$errors): int
{
    $value = $input[$field] ?? 0;
    if (!in_array($value, [0, 1, '0', '1', false, true], true)) {
        $errors[$field] = [$field . ' must be true or false.'];
        return 0;
    }

    return (int)(bool)$value;
}

function validateStringList(array $input, string $field, array &$errors): array
{
    $value = $input[$field] ?? [];
    if (is_string($value)) {
        $value = preg_split('/[\r\n,]+/', $value) ?: [];
    }
    if (!is_array($value)) {
        $errors[$field] = [$field . ' must be a list of strings.'];
        return [];
    }

    $items = [];
    foreach ($value as $item) {
        if (!is_string($item) && !is_numeric($item)) {
            $errors[$field] = [$field . ' must contain only strings.'];
            return [];
        }
        $item = trim((string)$item);
        if ($item !== '') {
            if (mb_strlen($item) > 255) {
                $errors[$field] = [$field . ' entries must not exceed 255 characters.'];
                return [];
            }
            $items[] = $item;
        }
    }

    return array_values(array_unique($items));
}

function decodeJsonField(?string $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function fetchWebsite(PDO $pdo, int $id, bool $activeOnly = false): ?array
{
    $sql = 'SELECT * FROM websites WHERE id = :id' . ($activeOnly ? ' AND active = 1' : '');
    $statement = $pdo->prepare($sql);
    $statement->execute(['id' => $id]);
    $website = $statement->fetch();
    if (!$website) {
        return null;
    }

    $website['technologies'] = decodeJsonField($website['technologies'] ?? null);
    $website['services'] = decodeJsonField($website['work_performed'] ?? null);
    unset($website['work_performed']);

    $images = $pdo->prepare('SELECT id, image_url, display_order FROM website_images WHERE website_id = :website_id ORDER BY display_order, id');
    $images->execute(['website_id' => $id]);
    $website['project_images'] = $images->fetchAll();

    return $website;
}

function fetchEntity(PDO $pdo, string $entity, ?int $id = null, bool $public = false): array|null
{
    if ($entity === 'hero') {
        if ($id !== null) {
            $statement = $pdo->prepare('SELECT * FROM hero_sections WHERE id = :id');
            $statement->execute(['id' => $id]);
            $hero = $statement->fetch();
            if ($hero) {
                $hero['heading'] = $hero['title'];
                $hero['subheading'] = $hero['subtitle'];
            }
            return $hero ?: null;
        }
        $where = $public ? ' WHERE active = 1' : '';
        $statement = $pdo->prepare('SELECT * FROM hero_sections' . $where . ' ORDER BY display_order, id');
        $statement->execute();
        $heroes = $statement->fetchAll();
        foreach ($heroes as &$hero) {
            $hero['heading'] = $hero['title'];
            $hero['subheading'] = $hero['subtitle'];
        }
        unset($hero);
        return $heroes;
    }

    if ($entity === 'websites') {
        if ($id !== null) {
            return fetchWebsite($pdo, $id, $public);
        }

        $where = $public ? ' WHERE active = 1' : '';
        $statement = $pdo->prepare('SELECT id FROM websites' . $where . ' ORDER BY display_order, id');
        $statement->execute();
        $items = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $websiteId) {
            $website = fetchWebsite($pdo, (int)$websiteId, $public);
            if ($website !== null) {
                $items[] = $website;
            }
        }
        return $items;
    }

    if ($entity === 'website-images') {
        if ($id !== null) {
            $statement = $pdo->prepare('SELECT * FROM website_images WHERE id = :id');
            $statement->execute(['id' => $id]);
            return $statement->fetch() ?: null;
        }
        $websiteId = filter_var($_GET['website_id'] ?? null, FILTER_VALIDATE_INT);
        if ($websiteId === false || $websiteId === null || $websiteId < 1) {
            jsonResponse(false, 'A valid website_id query parameter is required.', [], ['website_id' => ['A valid website_id is required.']], 422);
        }
        $statement = $pdo->prepare('SELECT * FROM website_images WHERE website_id = :website_id ORDER BY display_order, id');
        $statement->execute(['website_id' => $websiteId]);
        return $statement->fetchAll();
    }

    if ($entity === 'enquiries') {
        if ($id !== null) {
            $statement = $pdo->prepare('SELECT * FROM enquiries WHERE id = :id');
            $statement->execute(['id' => $id]);
            return $statement->fetch() ?: null;
        }
        $statement = $pdo->prepare('SELECT * FROM enquiries ORDER BY created_at DESC, id DESC');
        $statement->execute();
        return $statement->fetchAll();
    }

    $tables = [
        'videos' => 'videos',
        'other-work' => 'other_work',
        'services' => 'services',
    ];
    if (!isset($tables[$entity])) {
        return null;
    }

    $table = $tables[$entity];
    if ($id !== null) {
        $statement = $pdo->prepare("SELECT * FROM `$table` WHERE id = :id");
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        if ($record && $entity === 'videos') {
            $record['video_url'] = publicMediaPath($record['video_url'] ?? null, 'videos');
        } elseif ($record && $entity === 'services') {
            $record['image_url'] = publicMediaPath($record['image_url'] ?? null, 'services');
        }
        return $record ?: null;
    }

    $where = $public ? ' WHERE active = 1' : '';
    $statement = $pdo->prepare("SELECT * FROM `$table`" . $where . ' ORDER BY display_order, id');
    $statement->execute();
    $records = $statement->fetchAll();
    foreach ($records as &$record) {
        if ($entity === 'videos') {
            $record['video_url'] = publicMediaPath($record['video_url'] ?? null, 'videos');
        } elseif ($entity === 'services') {
            $record['image_url'] = publicMediaPath($record['image_url'] ?? null, 'services');
        }
    }
    unset($record);
    return $records;
}

function validateEntity(string $entity, array $input, array &$errors): array
{
    if ($entity === 'hero') {
        $hero = [
            'title' => validateText($input, 'title', 'Title', 255, true, $errors),
            'subtitle' => validateText($input, 'subtitle', 'Subtitle', 10000, false, $errors),
            'image_url' => validateUrl($input, 'image_url', 'Image URL', false, $errors),
            'video_url' => validateUrl($input, 'video_url', 'Video URL', false, $errors),
            'display_order' => validateOrder($input, $errors),
            'featured' => validateBoolean($input, 'featured', $errors),
            'active' => validateBoolean($input, 'active', $errors),
        ];
        if (($hero['image_url'] ?? null) === null && ($hero['video_url'] ?? null) === null) {
            $errors['image_url'] = ['Provide an image URL, a video URL, or both.'];
        }
        return $hero;
    }

    if ($entity === 'videos') {
        $category = validateText($input, 'category', 'Category', 100, true, $errors);
        $videoType = array_key_exists('video_type', $input)
            ? validateText($input, 'video_type', 'Video type', 30, false, $errors)
            : null;
        if ($category !== null && !in_array($category, ['Product Shoot', 'Brand Video', 'Advertisement', 'Social Media', 'Commercial', 'Other'], true)) {
            $errors['category'] = ['Select a valid video category.'];
        }
        if ($videoType !== null && $videoType !== '' && !in_array($videoType, ['youtube', 'vimeo', 'direct', 'other'], true)) {
            $errors['video_type'] = ['Video type must be YouTube, Vimeo, direct, or other.'];
        }
        $data = [
            'title' => validateText($input, 'title', 'Title', 255, true, $errors),
            'description' => validateText($input, 'description', 'Description', 10000, false, $errors),
            'category' => $category,
            'thumbnail' => validateUrl($input, 'thumbnail', 'Thumbnail URL', false, $errors),
            'video_url' => validateUrl($input, 'video_url', 'Video URL', false, $errors),
            'display_order' => validateOrder($input, $errors),
            'featured' => validateBoolean($input, 'featured', $errors),
            'active' => validateBoolean($input, 'active', $errors),
        ];
        if ($videoType !== null && $videoType !== '') {
            $data['video_type'] = $videoType;
        }
        return $data;
    }

    if ($entity === 'websites') {
        $websiteData = [
            'project_name' => validateText($input, 'project_name', 'Project title', 255, true, $errors),
            'client_name' => validateText($input, 'client_name', 'Client/project name', 255, true, $errors),
            'category' => validateText($input, 'category', 'Category', 100, true, $errors),
            'short_description' => validateText($input, 'short_description', 'Short description', 5000, false, $errors),
            'full_description' => validateText($input, 'full_description', 'Full description', 20000, false, $errors),
            'cover_image' => validateUrl($input, 'cover_image', 'Cover image URL', false, $errors),
            'live_url' => validateUrl($input, 'live_url', 'Live website URL', false, $errors),
            'technologies' => json_encode(validateStringList($input, 'technologies', $errors), JSON_THROW_ON_ERROR),
            'work_performed' => json_encode(validateStringList($input, 'services', $errors), JSON_THROW_ON_ERROR),
            'display_order' => validateOrder($input, $errors),
            'featured' => validateBoolean($input, 'featured', $errors),
            'active' => validateBoolean($input, 'active', $errors),
        ];

        if (array_key_exists('project_images', $input)) {
            $images = is_array($input['project_images']) ? $input['project_images'] : preg_split('/[\r\n]+/', (string)$input['project_images']);
            $validatedImages = [];
            foreach ($images ?: [] as $image) {
                if (!is_string($image)) {
                    $errors['project_images'] = ['Project image URLs must be strings.'];
                    continue;
                }
                $image = trim((string)$image);
                if ($image === '') {
                    continue;
                }
                $urlError = [];
                $validatedUrl = validateUrl(['image' => $image], 'image', 'Project image URL', true, $urlError);
                if ($validatedUrl === null) {
                    $errors['project_images'] = array_merge($errors['project_images'] ?? [], $urlError['image']);
                } else {
                    $validatedImages[] = $validatedUrl;
                }
            }
            $websiteData['project_images'] = array_values(array_unique($validatedImages));
        }

        return $websiteData;
    }

    if ($entity === 'other-work') {
        $mediaUrl = validateUrl($input, 'media_url', 'Image/media URL', false, $errors);
        if ($mediaUrl === null && !empty($input['image_url'])) {
            $mediaUrl = validateUrl($input, 'image_url', 'Image/media URL', false, $errors);
        }
        return [
            'title' => validateText($input, 'title', 'Title', 255, true, $errors),
            'description' => validateText($input, 'description', 'Description', 10000, false, $errors),
            'category' => validateText($input, 'category', 'Category', 100, true, $errors),
            'image_url' => $mediaUrl,
            'display_order' => validateOrder($input, $errors),
            'featured' => validateBoolean($input, 'featured', $errors),
            'active' => validateBoolean($input, 'active', $errors),
        ];
    }

    if ($entity === 'services') {
        return [
            'name' => validateText($input, 'name', 'Service name', 255, true, $errors),
            'short_description' => validateText($input, 'short_description', 'Short description', 5000, false, $errors),
            'full_description' => validateText($input, 'full_description', 'Full description', 20000, false, $errors),
            'image_url' => validateUrl($input, 'image_url', 'Image URL', false, $errors),
            'display_order' => validateOrder($input, $errors),
            'featured' => validateBoolean($input, 'featured', $errors),
            'active' => validateBoolean($input, 'active', $errors),
        ];
    }

    return [];
}

function saveEntity(PDO $pdo, string $entity, string $method, ?int $id, array $payload): array
{
    if ($entity === 'enquiries') {
        if ($method !== 'PUT' || $id === null) {
            jsonResponse(false, 'Only enquiry status updates are supported.', [], [], 405);
        }
        $status = is_string($payload['status'] ?? null) ? trim($payload['status']) : '';
        if (!in_array($status, ['New', 'Contacted', 'Completed'], true)) {
            jsonResponse(false, 'Validation failed.', [], ['status' => ['Status must be New, Contacted, or Completed.']], 422);
        }
        $statement = $pdo->prepare('UPDATE enquiries SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $id]);
        return fetchEntity($pdo, $entity, $id) ?? [];
    }

    if ($entity === 'website-images') {
        $errors = [];
        $websiteId = filter_var($payload['website_id'] ?? null, FILTER_VALIDATE_INT);
        if ($websiteId === false || $websiteId === null || $websiteId < 1) {
            $errors['website_id'] = ['A valid website_id is required.'];
        } elseif (!fetchWebsite($pdo, (int)$websiteId)) {
            $errors['website_id'] = ['The selected website does not exist.'];
        }
        $imageUrl = validateUrl($payload, 'image_url', 'Image URL', true, $errors);
        $order = validateOrder($payload, $errors);
        if ($errors) {
            jsonResponse(false, 'Validation failed.', [], $errors, 422);
        }

        if ($method === 'POST') {
            $statement = $pdo->prepare('INSERT INTO website_images (website_id, image_url, display_order) VALUES (:website_id, :image_url, :display_order)');
            $statement->execute(['website_id' => $websiteId, 'image_url' => $imageUrl, 'display_order' => $order]);
            return fetchEntity($pdo, $entity, (int)$pdo->lastInsertId()) ?? [];
        }

        $statement = $pdo->prepare('UPDATE website_images SET website_id = :website_id, image_url = :image_url, display_order = :display_order WHERE id = :id');
        $statement->execute(['website_id' => $websiteId, 'image_url' => $imageUrl, 'display_order' => $order, 'id' => $id]);
        return fetchEntity($pdo, $entity, (int)$id) ?? [];
    }

    $errors = [];
    $data = validateEntity($entity, $payload, $errors);
    $mediaUpload = null;
    $existingMedia = [];
    $mediaKind = in_array($entity, ['videos', 'services'], true) ? $entity : null;
    if ($mediaKind !== null) {
        $mediaUpload = inspectMediaUpload($mediaKind, $errors);
        if ($id !== null) {
            $columns = $entity === 'videos' ? 'video_url, video_type, thumbnail' : 'image_url';
            $statement = $pdo->prepare("SELECT $columns FROM `$entity` WHERE id = :id");
            $statement->execute(['id' => $id]);
            $existingMedia = $statement->fetch() ?: [];
        }
    }
    if ($errors) {
        jsonResponse(false, 'Validation failed.', [], $errors, 422);
    }

    if ($entity === 'videos') {
        if ($mediaUpload !== null) {
            $data['video_url'] = storeMediaUpload($mediaUpload);
            $data['video_type'] = 'direct';
        } else {
            if ($id !== null && !array_key_exists('video_url', $payload)) {
                $data['video_url'] = $existingMedia['video_url'] ?? null;
            }
            if ($id !== null && !array_key_exists('video_type', $payload)) {
                $data['video_type'] = $existingMedia['video_type'] ?? 'other';
            } elseif (!isset($data['video_type'])) {
                $data['video_type'] = 'other';
            }
            if ($id !== null && !array_key_exists('thumbnail', $payload)) {
                $data['thumbnail'] = $existingMedia['thumbnail'] ?? null;
            }
        }
        if (($data['video_url'] ?? null) === null || $data['video_url'] === '') {
            jsonResponse(false, 'Validation failed.', [], ['video' => ['Choose a video file.']], 422);
        }
    } elseif ($entity === 'services') {
        if ($mediaUpload !== null) {
            $data['image_url'] = storeMediaUpload($mediaUpload);
        } elseif ($id !== null && !array_key_exists('image_url', $payload)) {
            $data['image_url'] = $existingMedia['image_url'] ?? null;
        }
    }

    $newUploadPath = $mediaUpload !== null ? $data[$entity === 'videos' ? 'video_url' : 'image_url'] : null;
    try {
        $pdo->beginTransaction();
        if ($entity === 'websites') {
            $images = $data['project_images'] ?? null;
            unset($data['project_images']);
        } else {
            $images = null;
        }

        if ($entity === 'hero') {
            $table = 'hero_sections';
        } elseif ($entity === 'other-work') {
            $table = 'other_work';
        } else {
            $table = $entity;
        }

        if ($method === 'POST') {
            $columns = array_keys($data);
            $quotedColumns = array_map(static fn(string $column): string => '`' . $column . '`', $columns);
            $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
            $statement = $pdo->prepare('INSERT INTO `' . $table . '` (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')');
            $statement->execute($data);
            $id = (int)$pdo->lastInsertId();
        } else {
            $assignments = array_map(static fn(string $column): string => '`' . $column . '` = :' . $column, array_keys($data));
            $data['id'] = $id;
            $statement = $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $assignments) . ' WHERE id = :id');
            $statement->execute($data);
        }

        if ($entity === 'websites' && $images !== null) {
            $deleteImages = $pdo->prepare('DELETE FROM website_images WHERE website_id = :website_id');
            $deleteImages->execute(['website_id' => $id]);
            $insertImage = $pdo->prepare('INSERT INTO website_images (website_id, image_url, display_order) VALUES (:website_id, :image_url, :display_order)');
            foreach ($images as $index => $imageUrl) {
                $insertImage->execute(['website_id' => $id, 'image_url' => $imageUrl, 'display_order' => $index + 1]);
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_string($newUploadPath) && str_starts_with($newUploadPath, 'uploads/')) {
            removeStoredMediaFile($newUploadPath);
        }
        throw $exception;
    }

    return fetchEntity($pdo, $entity, (int)$id) ?? [];
}

function deleteEntity(PDO $pdo, string $entity, int $id): void
{
    $tables = [
        'hero' => 'hero_sections',
        'videos' => 'videos',
        'websites' => 'websites',
        'website-images' => 'website_images',
        'other-work' => 'other_work',
        'services' => 'services',
        'enquiries' => 'enquiries',
    ];
    if (!isset($tables[$entity])) {
        jsonResponse(false, 'Route not found.', [], [], 404);
    }

    $statement = $pdo->prepare('DELETE FROM `' . $tables[$entity] . '` WHERE id = :id');
    $statement->execute(['id' => $id]);
    if ($statement->rowCount() === 0) {
        jsonResponse(false, 'Resource not found.', [], ['id' => ['Resource not found.']], 404);
    }
}

function adminDashboardStats(PDO $pdo): array
{
    $counts = [];
    $queries = [
        'videos' => 'SELECT COUNT(*) FROM videos',
        'websites' => 'SELECT COUNT(*) FROM websites',
        'other_work' => 'SELECT COUNT(*) FROM other_work',
        'services' => 'SELECT COUNT(*) FROM services',
        'new_enquiries' => "SELECT COUNT(*) FROM enquiries WHERE status = 'New'",
    ];
    foreach ($queries as $key => $sql) {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $counts[$key] = (int)$statement->fetchColumn();
    }

    return $counts;
}

function verifyAdminLogin(PDO $pdo, string $email, string $password): ?array
{
    $statement = $pdo->prepare('SELECT id, email, password_hash FROM admin_users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => strtolower(trim($email))]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    return ['id' => (int)$user['id'], 'email' => $user['email']];
}

function handleAdminApi(PDO $pdo, array $segments, string $method): never
{
    requireAdmin();

    if (($segments[2] ?? null) === 'dashboard' && $method === 'GET') {
        jsonResponse(true, 'Dashboard ready.', adminDashboardStats($pdo));
    }

    $entity = $segments[2] ?? '';
    $idText = $segments[3] ?? null;
    $subresource = $segments[4] ?? null;
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($method === 'POST' && str_contains($contentType, 'multipart/form-data')) {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postLimit = phpIniBytes('post_max_size');
        if ($postLimit > 0 && $contentLength > $postLimit) {
            jsonResponse(false, 'Upload exceeds the PHP request size limit.', [], ['upload' => ['Increase PHP post_max_size to accept this upload.']], 413);
        }
        if ($contentLength > 110 * 1024 * 1024) {
            jsonResponse(false, 'Upload exceeds the request size limit.', [], ['upload' => ['The request must be smaller than 110 MB.']], 413);
        }
    }
    if ($entity === 'websites' && $idText !== null && $subresource === 'images') {
        $websiteId = filter_var($idText, FILTER_VALIDATE_INT);
        if ($websiteId === false || $websiteId < 1) {
            jsonResponse(false, 'Invalid website ID.', [], ['id' => ['Website ID must be a positive integer.']], 422);
        }
        if ($method === 'GET') {
            $website = fetchWebsite($pdo, (int)$websiteId);
            if ($website === null) {
                jsonResponse(false, 'Website not found.', [], ['website_id' => ['Website not found.']], 404);
            }
            jsonResponse(true, 'Website images retrieved.', $website['project_images']);
        }
        if ($method === 'POST') {
            $payload = readRequestBody();
            $payload['website_id'] = $websiteId;
            $image = saveEntity($pdo, 'website-images', 'POST', null, $payload);
            jsonResponse(true, 'Website image created.', $image, [], 201);
        }
    }

    if (!in_array($entity, ['hero', 'videos', 'websites', 'website-images', 'other-work', 'services', 'enquiries'], true)) {
        jsonResponse(false, 'Route not found.', [], [], 404);
    }
    $id = null;
    if ($idText !== null) {
        $id = filter_var($idText, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            jsonResponse(false, 'Invalid resource ID.', [], ['id' => ['ID must be a positive integer.']], 422);
        }
    }

    if ($method === 'GET') {
        $records = fetchEntity($pdo, $entity, $id);
        if ($records === null) {
            jsonResponse(false, 'Resource not found.', [], ['id' => ['Resource not found.']], 404);
        }
        jsonResponse(true, 'Resource retrieved.', $records);
    }
    if ($method === 'POST') {
        if ($id !== null && in_array($entity, ['videos', 'services'], true)
            && str_contains($contentType, 'multipart/form-data')) {
            if (fetchEntity($pdo, $entity, $id) === null) {
                jsonResponse(false, 'Resource not found.', [], ['id' => ['Resource not found.']], 404);
            }
            $record = saveEntity($pdo, $entity, 'PUT', $id, $_POST);
            jsonResponse(true, 'Resource updated.', $record);
        }
        if ($id !== null || $entity === 'enquiries') {
            jsonResponse(false, 'Method not allowed.', [], [], 405);
        }
        $payload = str_contains($contentType, 'multipart/form-data') ? $_POST : readRequestBody();
        $record = saveEntity($pdo, $entity, 'POST', null, $payload);
        jsonResponse(true, 'Resource created.', $record, [], 201);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        if ($id === null) {
            jsonResponse(false, 'A resource ID is required.', [], ['id' => ['A resource ID is required.']], 422);
        }
        if (fetchEntity($pdo, $entity, $id) === null) {
            jsonResponse(false, 'Resource not found.', [], ['id' => ['Resource not found.']], 404);
        }
        $record = saveEntity($pdo, $entity, $method === 'PATCH' ? 'PUT' : $method, $id, readRequestBody());
        jsonResponse(true, 'Resource updated.', $record);
    }
    if ($method === 'DELETE') {
        if ($id === null) {
            jsonResponse(false, 'A resource ID is required.', [], ['id' => ['A resource ID is required.']], 422);
        }
        deleteEntity($pdo, $entity, $id);
        jsonResponse(true, 'Resource deleted.', []);
    }

    jsonResponse(false, 'Method not allowed.', [], [], 405);
}

function handlePublicApi(): never
{
    $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $segments = array_values(array_filter(explode('/', $path), static fn(string $segment): bool => $segment !== ''));
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (($segments[0] ?? '') !== 'api') {
        if (($segments[0] ?? null) === 'media') {
            serveUploadedMedia($segments, $method);
        }
        if (($segments[0] ?? null) === 'admin') {
            $adminRoot = dirname(__DIR__) . '/admin';
            if (($segments[1] ?? null) === 'login') {
                require $adminRoot . '/login.php';
                exit;
            }
            if (($segments[1] ?? null) === 'logout') {
                require $adminRoot . '/logout.php';
                exit;
            }
            require $adminRoot . '/index.php';
            exit;
        }
        jsonResponse(false, 'Route not found.', [], [], 404);
    }

    try {
        $resource = $segments[1] ?? '';

        if ($resource === 'media') {
            serveUploadedMedia([
                'media',
                $segments[2] ?? '',
                is_string($_GET['file'] ?? null) ? $_GET['file'] : '',
            ], $method);
        }

        if ($resource === 'admin' && ($segments[2] ?? '') === 'login' && $method === 'POST') {
            $pdo = database();
            $payload = readRequestBody();
            $email = is_string($payload['email'] ?? null) ? trim($payload['email']) : '';
            $password = is_string($payload['password'] ?? null) ? $payload['password'] : '';
            $errors = [];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = ['A valid email is required.'];
            }
            if ($password === '') {
                $errors['password'] = ['Password is required.'];
            }
            if ($errors) {
                jsonResponse(false, 'Validation failed.', [], $errors, 422);
            }
            $user = verifyAdminLogin($pdo, $email, $password);
            if ($user === null) {
                jsonResponse(false, 'Invalid credentials.', [], ['auth' => ['Email or password is incorrect.']], 401);
            }
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_email'] = $user['email'];
            jsonResponse(true, 'Login successful.', ['email' => $user['email']]);
        }

        if ($resource === 'admin' && ($segments[2] ?? '') === 'logout' && $method === 'POST') {
            requireAdmin();
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            jsonResponse(true, 'Logged out successfully.');
        }

        if ($resource === 'admin') {
            requireAdmin();
            $pdo = database();
            handleAdminApi($pdo, $segments, $method);
        }

        $pdo = database();
        if ($method === 'GET') {
            $publicEntity = [
                'hero' => 'hero',
                'videos' => 'videos',
                'websites' => 'websites',
                'other-work' => 'other-work',
                'services' => 'services',
            ][$resource] ?? null;
            if ($publicEntity !== null) {
                $id = null;
                if ($resource === 'websites' && isset($segments[2])) {
                    $id = filter_var($segments[2], FILTER_VALIDATE_INT);
                    if ($id === false || $id < 1) {
                        jsonResponse(false, 'Invalid website ID.', [], ['id' => ['Website ID must be a positive integer.']], 422);
                    }
                } elseif (isset($segments[2])) {
                    jsonResponse(false, 'Route not found.', [], [], 404);
                }
                $records = fetchEntity($pdo, $publicEntity, $id, true);
                if ($records === null) {
                    jsonResponse(false, 'Website not found.', [], ['id' => ['Website not found.']], 404);
                }
                jsonResponse(true, 'Content retrieved.', $records);
            }
        }

        if ($resource === 'enquiries' && $method === 'POST') {
            $payload = readRequestBody();
            $errors = [];
            $name = validateText($payload, 'name', 'Name', 255, true, $errors);
            $email = is_string($payload['email'] ?? null) ? trim($payload['email']) : '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = ['A valid email is required.'];
            }
            $phone = validateText($payload, 'phone', 'Phone', 100, false, $errors);
            $company = validateText($payload, 'company', 'Company', 255, false, $errors);
            $projectType = validateText($payload, 'project_type', 'Project type', 100, false, $errors);
            $message = validateText($payload, 'message', 'Message', 20000, true, $errors);
            if ($errors) {
                jsonResponse(false, 'Validation failed.', [], $errors, 422);
            }
            $statement = $pdo->prepare('INSERT INTO enquiries (name, email, phone, company, project_type, message, status) VALUES (:name, :email, :phone, :company, :project_type, :message, :status)');
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'project_type' => $projectType,
                'message' => $message,
                'status' => 'New',
            ]);
            $id = (int)$pdo->lastInsertId();
            $enquiry = fetchEntity($pdo, 'enquiries', $id);
            jsonResponse(true, 'Enquiry submitted successfully.', $enquiry, [], 201);
        }

        jsonResponse(false, 'Route not found.', [], [], 404);
    } catch (PDOException $exception) {
        error_log('Portfolio database error: ' . $exception->getMessage());
        jsonResponse(false, 'The database request could not be completed.', [], [], 500);
    } catch (Throwable $exception) {
        error_log('Portfolio API error: ' . $exception->getMessage());
        jsonResponse(false, 'The server could not complete the request.', [], [], 500);
    }
}
