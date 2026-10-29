<?php
/**
 * 用户日志文件的安全存储。
 */

function storage_root(): string
{
    $root = rtrim((string)kykky_cfg('storage.path', '/var/lib/kykky/storage'), '/');
    if ($root === '') {
        throw new RuntimeException('storage.path 未配置');
    }
    if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
        throw new RuntimeException('无法创建存储目录，请检查权限: ' . $root);
    }
    return $root;
}

function storage_user_dir(int $userId): string
{
    return storage_root() . '/' . $userId;
}

function storage_ensure_layout(): void
{
    $root = storage_root();
    foreach (['.staging', '.backup', '.locks'] as $dir) {
        $path = $root . '/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('无法创建存储子目录: ' . $path);
        }
    }
}

function storage_valid_filename(string $name): bool
{
    return $name === 'history.gz' || preg_match('/^metrics_reader_[0-9]{1,10}$/', $name) === 1;
}

/**
 * 把 $_FILES['logs'] 规范化为列表，兼容单文件和多文件。
 *
 * @return array<int, array{name:string, tmp_name:string, size:int, error:int}>
 */
function normalize_uploaded_files(string $field = 'logs'): array
{
    if (!isset($_FILES[$field])) {
        return [];
    }
    $raw = $_FILES[$field];
    if (!isset($raw['name'])) {
        return [];
    }

    $files = [];
    if (is_array($raw['name'])) {
        foreach ($raw['name'] as $key => $name) {
            $files[] = [
                'name' => basename((string)$name),
                'tmp_name' => (string)($raw['tmp_name'][$key] ?? ''),
                'size' => (int)($raw['size'][$key] ?? 0),
                'error' => (int)($raw['error'][$key] ?? UPLOAD_ERR_NO_FILE),
            ];
        }
    } else {
        $files[] = [
            'name' => basename((string)$raw['name']),
            'tmp_name' => (string)($raw['tmp_name'] ?? ''),
            'size' => (int)($raw['size'] ?? 0),
            'error' => (int)($raw['error'] ?? UPLOAD_ERR_NO_FILE),
        ];
    }
    return $files;
}

function upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件超过服务器上传限制',
        UPLOAD_ERR_PARTIAL => '文件上传不完整',
        UPLOAD_ERR_NO_FILE => '没有选择文件',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
        UPLOAD_ERR_CANT_WRITE => '服务器无法写入临时文件',
        UPLOAD_ERR_EXTENSION => '上传被服务器扩展阻止',
        default => '文件上传失败',
    };
}

/**
 * 校验单个上传文件，返回 metrics/history 类型。
 */
function validate_uploaded_log(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(upload_error_message((int)$file['error']));
    }

    $name = (string)$file['name'];
    if (!storage_valid_filename($name)) {
        throw new InvalidArgumentException('非法文件名: ' . $name);
    }

    $tmp = (string)$file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new InvalidArgumentException('上传临时文件无效');
    }

    $size = (int)$file['size'];
    $isHistory = $name === 'history.gz';
    $maxSize = (int)kykky_cfg($isHistory ? 'limits.history_max_bytes' : 'limits.metrics_max_bytes');
    if ($size <= 0 || $size > $maxSize) {
        throw new InvalidArgumentException('文件大小不合法: ' . $name);
    }

    if ($isHistory) {
        $fh = fopen($tmp, 'rb');
        $magic = $fh === false ? false : fread($fh, 2);
        if ($fh !== false) {
            fclose($fh);
        }
        if ($magic !== "\x1f\x8b") {
            throw new InvalidArgumentException('history.gz 不是有效的 Gzip 文件');
        }
        $gz = @gzopen($tmp, 'rb');
        if ($gz === false) {
            throw new InvalidArgumentException('history.gz 无法解压');
        }
        $probe = gzread($gz, 1024);
        gzclose($gz);
        if ($probe === false) {
            throw new InvalidArgumentException('history.gz 内容损坏');
        }
        return 'history';
    }

    $fh = fopen($tmp, 'rb');
    $sample = $fh === false ? false : fread($fh, 4096);
    if ($fh !== false) {
        fclose($fh);
    }
    if ($sample === false || strpos($sample, 'com.lab126.booklet.reader.activeDuration') === false) {
        throw new InvalidArgumentException('日志文件格式不正确: ' . $name);
    }
    return 'metrics';
}

/**
 * 原子替换用户日志目录。上传前已完成全部校验。
 *
 * @param array<int, array{name:string, tmp_name:string, size:int, error:int}> $files
 */
function commit_uploaded_files(int $userId, array $files): int
{
    if ($userId <= 0) {
        throw new InvalidArgumentException('用户 ID 无效');
    }
    storage_ensure_layout();
    $root = storage_root();
    $lockPath = $root . '/.locks/' . $userId . '.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException('无法创建上传锁');
    }

    $staging = $root . '/.staging/' . $userId . '-' . bin2hex(random_bytes(8));
    $backup = null;
    $dest = storage_user_dir($userId);
    $moved = 0;

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('无法锁定上传目录');
        }
        if (!mkdir($staging, 0750, true) && !is_dir($staging)) {
            throw new RuntimeException('无法创建临时上传目录');
        }

        foreach ($files as $file) {
            $name = (string)$file['name'];
            $target = $staging . '/' . $name;
            if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
                throw new RuntimeException('保存文件失败: ' . $name);
            }
            @chmod($target, 0640);
            $moved++;
        }

        if ($moved === 0) {
            throw new InvalidArgumentException('没有可保存的文件');
        }

        if (is_link($dest)) {
            throw new RuntimeException('用户存储目录不能是符号链接');
        }
        if (is_dir($dest)) {
            $backup = $root . '/.backup/' . $userId . '-' . bin2hex(random_bytes(8));
            if (!rename($dest, $backup)) {
                throw new RuntimeException('无法备份旧日志目录');
            }
        }

        if (!rename($staging, $dest)) {
            if ($backup !== null && is_dir($backup)) {
                @rename($backup, $dest);
            }
            throw new RuntimeException('无法启用新日志目录');
        }
        $staging = '';

        if ($backup !== null && is_dir($backup)) {
            storage_delete_tree($backup);
        }

        return $moved;
    } finally {
        if ($staging !== '' && is_dir($staging)) {
            storage_delete_tree($staging);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function storage_delete_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . '/' . $item;
        if (is_link($child) || is_file($child)) {
            @unlink($child);
        } elseif (is_dir($child)) {
            storage_delete_tree($child);
        }
    }
    @rmdir($path);
}

/**
 * @return array<int, array{name:string,size:int,mtime:int}>
 */
function storage_list_files(int $userId): array
{
    $dir = storage_user_dir($userId);
    if (!is_dir($dir)) {
        return [];
    }
    $items = scandir($dir);
    if ($items === false) {
        return [];
    }
    $files = [];
    foreach ($items as $name) {
        if ($name === '.' || $name === '..' || !storage_valid_filename($name)) {
            continue;
        }
        $path = $dir . '/' . $name;
        if (!is_file($path) || is_link($path)) {
            continue;
        }
        $files[] = [
            'name' => $name,
            'size' => (int)filesize($path),
            'mtime' => (int)filemtime($path),
        ];
    }
    usort($files, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    return $files;
}

function storage_file_path(int $userId, string $name): string
{
    if (!storage_valid_filename($name)) {
        throw new InvalidArgumentException('非法文件名');
    }
    $dir = storage_user_dir($userId);
    $path = $dir . '/' . $name;
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('文件不存在');
    }
    // 再次确认路径没有逃逸出用户目录。
    $realDir = realpath($dir);
    $realPath = realpath($path);
    if ($realDir === false || $realPath === false || dirname($realPath) !== $realDir) {
        throw new RuntimeException('文件路径无效');
    }
    return $realPath;
}
