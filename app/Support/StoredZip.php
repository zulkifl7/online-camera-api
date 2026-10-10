<?php

namespace App\Support;

use RuntimeException;

class StoredZip
{
    /**
     * Write an uncompressed ZIP so archives work even when the PHP zip
     * extension is unavailable. Entries are streamed from disk.
     *
     * @param  list<array{name: string, path: string}>  $files
     */
    public static function create(string $destination, array $files): void
    {
        $out = fopen($destination, 'wb');

        if ($out === false) {
            throw new RuntimeException('The download archive could not be created.');
        }

        $entries = [];

        try {
            foreach ($files as $file) {
                $entries[] = self::appendStoredFile($out, $file['name'], $file['path']);
            }

            $centralStart = ftell($out);
            $central = '';

            foreach ($entries as $entry) {
                $nameLen = strlen($entry['name']);
                $central .= pack(
                    'VvvvvvvVVVvvvvvVV',
                    0x02014b50,
                    20,
                    20,
                    0,
                    0,
                    0,
                    0,
                    $entry['crc'],
                    $entry['size'],
                    $entry['size'],
                    $nameLen,
                    0,
                    0,
                    0,
                    0,
                    0,
                    $entry['offset'],
                );
                $central .= $entry['name'];
            }

            fwrite($out, $central);
            $centralLength = strlen($central);

            fwrite($out, pack(
                'VvvvvVVv',
                0x06054b50,
                0,
                0,
                count($entries),
                count($entries),
                $centralLength,
                $centralStart,
                0,
            ));
        } finally {
            fclose($out);
        }
    }

    /**
     * @return array{name: string, crc: int, size: int, offset: int}
     */
    private static function appendStoredFile($out, string $name, string $sourcePath): array
    {
        $name = str_replace('\\', '/', $name);
        $size = filesize($sourcePath);

        if ($size === false) {
            throw new RuntimeException('A photo could not be added to the archive.');
        }

        $crc = hexdec((string) hash_file('crc32b', $sourcePath));
        $offset = ftell($out);
        $nameLen = strlen($name);

        fwrite($out, pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            0,
            0,
            $crc,
            $size,
            $size,
            $nameLen,
            0,
        ));
        fwrite($out, $name);

        $in = fopen($sourcePath, 'rb');

        if ($in === false) {
            throw new RuntimeException('A photo could not be added to the archive.');
        }

        try {
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
        }

        return [
            'name' => $name,
            'crc' => $crc,
            'size' => $size,
            'offset' => $offset === false ? 0 : $offset,
        ];
    }
}
