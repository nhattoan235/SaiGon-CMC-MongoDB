<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupArchive
{
    private const CHUNK = 1048576;

    public function key(): string
    {
        $key = base64_decode((string) config('backup.key'), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Chưa cấu hình khóa backup 32 byte hợp lệ.');
        }

        return $key;
    }

    public function keyId(): string
    {
        return substr(hash('sha256', $this->key()), 0, 16);
    }

    public function pack(string $captureRoot, string $destination): void
    {
        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Không tạo được gói backup.');
        }
        try {
            foreach (File::allFiles($captureRoot) as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('Không sao lưu symlink.');
                }
                $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
            }
        } finally {
            $zip->close();
        }
    }

    public function encrypt(string $source, string $destination): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key());
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'xb');
        if (! $input || ! $output) {
            throw new RuntimeException('Không mở được gói backup.');
        }
        try {
            $this->write($output, 'CMC1'.$header);
            do {
                $plain = fread($input, self::CHUNK);
                if ($plain === false) {
                    throw new RuntimeException('Không đọc được gói backup.');
                }
                $tag = feof($input) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, '', $tag);
                $this->write($output, pack('N', strlen($cipher)).$cipher);
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($input);
            fclose($output);
            @chmod($destination, 0600);
        }
    }

    public function unpackVerified(string $artifact, string $destination): array
    {
        if (file_exists($destination)) {
            throw new RuntimeException('Thư mục phục hồi phải chưa tồn tại.');
        }
        $temporary = tempnam(dirname($artifact), '.verify-');
        $zip = new ZipArchive;
        $opened = false;
        try {
            $this->decrypt($artifact, $temporary);
            if ($zip->open($temporary) !== true) {
                throw new RuntimeException('Gói backup không hợp lệ.');
            }
            $opened = true;
            $size = 0;
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if (! $this->safeName($name) || (($attributes >> 16) & 0170000) === 0120000 || isset($names[strtolower($name)])) {
                    throw new RuntimeException('Đường dẫn trong backup không an toàn.');
                }
                $names[strtolower($name)] = true;
                $size += $entry['size'];
                if ($size > config('backup.max_extract_bytes') || $zip->numFiles > 100000) {
                    throw new RuntimeException('Gói backup vượt giới hạn giải nén.');
                }
            }
            $raw = $zip->getFromName('manifest.json', 4194304);
            $manifest = $raw === false ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 1 || ($manifest['application'] ?? null) !== 'saigon-cmc' || ! is_array($manifest['files'] ?? null) || ! isset($manifest['files']['database.archive.gz'])) {
                throw new RuntimeException('Manifest backup không tương thích.');
            }
            foreach ($manifest['files'] as $name => $hash) {
                if (! $this->safeName($name) || ! isset($names[strtolower($name)]) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                    throw new RuntimeException('Manifest không khớp gói backup.');
                }
            }
            if (count($names) !== count($manifest['files']) + 1) {
                throw new RuntimeException('Backup có tệp ngoài manifest.');
            }
            File::ensureDirectoryExists($destination, 0700);
            foreach (array_merge(['manifest.json' => null], $manifest['files']) as $name => $hash) {
                $path = $destination.'/'.$name;
                File::ensureDirectoryExists(dirname($path), 0700);
                $stream = $zip->getStream($name);
                $output = fopen($path, 'xb');
                if (! $stream || ! $output) {
                    throw new RuntimeException('Không giải nén được backup.');
                }
                try {
                    stream_copy_to_stream($stream, $output);
                } finally {
                    fclose($stream);
                    fclose($output);
                }
                if ($hash !== null && ! hash_equals($hash, hash_file('sha256', $path))) {
                    throw new RuntimeException('Hash file backup không đúng.');
                }
            }

            return $manifest;
        } catch (\Throwable $exception) {
            File::deleteDirectory($destination);
            throw $exception;
        } finally {
            if ($opened) {
                @$zip->close();
            }
            @unlink($temporary);
        }
    }

    private function safeName(string $name): bool
    {
        return $name === 'manifest.json' || $name === 'database.archive.gz'
            || (preg_match('#^files/(private|public)/[^\\\\:\x00]+$#D', $name)
                && ! preg_match('#(^|/)(\.{1,2})(/|$)#', $name)
                && ! preg_match('#[. ](/|$)#', $name));
    }

    private function decrypt(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if (! $input || ! $output) {
            throw new RuntimeException('Không đọc được backup.');
        }
        try {
            $header = fread($input, 4 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (strlen($header) !== 28 || substr($header, 0, 4) !== 'CMC1') {
                throw new RuntimeException('Định dạng backup không hợp lệ.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($header, 4), $this->key());
            $final = false;
            $total = 0;
            while (! feof($input)) {
                $prefix = fread($input, 4);
                if ($prefix === '' && $final) {
                    break;
                }
                if (strlen($prefix) !== 4 || $final) {
                    throw new RuntimeException('Backup thiếu dữ liệu hoặc sai thứ tự.');
                }
                $length = unpack('N', $prefix)[1];
                if ($length < 17 || $length > self::CHUNK + 17) {
                    throw new RuntimeException('Kích thước khối backup không hợp lệ.');
                }
                $cipher = '';
                while (strlen($cipher) < $length && ! feof($input)) {
                    $cipher .= fread($input, $length - strlen($cipher));
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('Backup bị sửa, hỏng hoặc sai khóa.');
                }
                [$plain, $tag] = $result;
                $total += strlen($plain);
                if ($total > config('backup.max_extract_bytes')) {
                    throw new RuntimeException('Backup vượt giới hạn.');
                }
                $this->write($output, $plain);
                $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            if (! $final) {
                throw new RuntimeException('Backup chưa hoàn chỉnh.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function write(mixed $stream, string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($stream, $bytes);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Không ghi được backup; kiểm tra dung lượng ổ đĩa.');
            }
            $bytes = substr($bytes, $written);
        }
    }
}
