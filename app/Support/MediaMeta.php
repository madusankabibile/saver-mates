<?php

namespace App\Support;

/**
 * Reads the little metadata the media features need without ffmpeg:
 * MP4 duration from the mvhd box, and MP3 tags, artwork and duration from
 * ID3v2 / ID3v1 and the first MPEG frame (Xing / VBRI aware).
 */
class MediaMeta
{
    /* ---------------------------------------------------------------- */
    /* Formatting                                                        */
    /* ---------------------------------------------------------------- */

    public static function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    public static function compactNumber(int $n): string
    {
        return match (true) {
            $n >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.').'M',
            $n >= 1_000 => rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.').'K',
            default => (string) $n,
        };
    }

    /* ---------------------------------------------------------------- */
    /* MP4 / MOV / M4A                                                   */
    /* ---------------------------------------------------------------- */

    /** Duration in seconds from the movie header box, or null when not an ISO media file. */
    public static function mp4Duration(string $path): ?int
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return null;
        }

        try {
            $size = filesize($path);
            $moov = self::findBox($fh, 0, $size, 'moov');
            if (! $moov) {
                return null;
            }

            $mvhd = self::findBox($fh, $moov[0], $moov[1], 'mvhd');
            if (! $mvhd) {
                return null;
            }

            fseek($fh, $mvhd[0]);
            $version = ord(fread($fh, 1));
            fread($fh, 3); // flags

            if ($version === 1) {
                fread($fh, 16);
                $timescale = unpack('N', fread($fh, 4))[1];
                $duration = unpack('J', fread($fh, 8))[1];
            } else {
                fread($fh, 8);
                $timescale = unpack('N', fread($fh, 4))[1];
                $duration = unpack('N', fread($fh, 4))[1];
            }

            return $timescale > 0 ? (int) round($duration / $timescale) : null;
        } finally {
            fclose($fh);
        }
    }

    /**
     * Walk sibling boxes between $start and $end. Returns [payloadStart, payloadEnd] of the first match.
     */
    protected static function findBox($fh, int $start, int $end, string $type): ?array
    {
        $pos = $start;

        while ($pos + 8 <= $end) {
            fseek($fh, $pos);
            $header = fread($fh, 8);
            if (strlen($header) < 8) {
                return null;
            }

            $boxSize = unpack('N', substr($header, 0, 4))[1];
            $boxType = substr($header, 4, 4);
            $headerLen = 8;

            if ($boxSize === 1) {
                $boxSize = unpack('J', fread($fh, 8))[1];
                $headerLen = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $end - $pos;
            }

            if ($boxSize < $headerLen) {
                return null;
            }

            if ($boxType === $type) {
                return [$pos + $headerLen, $pos + $boxSize];
            }

            $pos += $boxSize;
        }

        return null;
    }

    /* ---------------------------------------------------------------- */
    /* MP3                                                               */
    /* ---------------------------------------------------------------- */

    /**
     * @return array{title:?string, artist:?string, album:?string, genre:?string, cover:?array{data:string,mime:string}, duration:?int}
     */
    public static function mp3Info(string $path): array
    {
        $info = ['title' => null, 'artist' => null, 'album' => null, 'genre' => null, 'cover' => null, 'duration' => null];

        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return $info;
        }

        try {
            $fileSize = filesize($path);
            $audioStart = 0;

            $header = fread($fh, 10);
            if (strlen($header) === 10 && substr($header, 0, 3) === 'ID3') {
                $major = ord($header[3]);
                $tagSize = self::syncsafe(substr($header, 6, 4));
                $audioStart = 10 + $tagSize + ((ord($header[5]) & 0x10) ? 10 : 0);

                $tag = fread($fh, min($tagSize, 16 * 1024 * 1024));
                $info = array_merge($info, self::parseId3v2($tag, $major, ord($header[5])));
            }

            $hasV1 = false;
            if ($fileSize > 128) {
                fseek($fh, $fileSize - 128);
                $v1 = fread($fh, 128);
                if (substr($v1, 0, 3) === 'TAG') {
                    $hasV1 = true;
                    $clean = fn ($s) => self::toUtf8(rtrim($s, "\0 ")) ?: null;
                    $info['title'] ??= $clean(substr($v1, 3, 30));
                    $info['artist'] ??= $clean(substr($v1, 33, 30));
                    $info['album'] ??= $clean(substr($v1, 63, 30));
                }
            }

            $info['duration'] = self::mp3Duration($fh, $audioStart, $fileSize - ($hasV1 ? 128 : 0));
        } finally {
            fclose($fh);
        }

        return $info;
    }

    protected static function syncsafe(string $bytes): int
    {
        $b = array_values(unpack('C4', $bytes));

        return ($b[0] << 21) | ($b[1] << 14) | ($b[2] << 7) | $b[3];
    }

    protected static function parseId3v2(string $tag, int $major, int $flags): array
    {
        $out = [];
        $pos = 0;
        $len = strlen($tag);

        // Skip an extended header if present.
        if ($flags & 0x40 && $len > 4) {
            $ext = $major === 4 ? self::syncsafe(substr($tag, 0, 4)) : unpack('N', substr($tag, 0, 4))[1] + 4;
            $pos = $ext;
        }

        $map = $major === 2
            ? ['TT2' => 'title', 'TP1' => 'artist', 'TAL' => 'album', 'TCO' => 'genre', 'PIC' => 'cover']
            : ['TIT2' => 'title', 'TPE1' => 'artist', 'TALB' => 'album', 'TCON' => 'genre', 'APIC' => 'cover'];

        $idLen = $major === 2 ? 3 : 4;
        $headLen = $major === 2 ? 6 : 10;

        while ($pos + $headLen <= $len) {
            $id = substr($tag, $pos, $idLen);
            if (! preg_match('/^[A-Z0-9]+$/', $id)) {
                break; // padding
            }

            if ($major === 2) {
                $size = unpack('N', "\0".substr($tag, $pos + 3, 3))[1];
            } elseif ($major === 4) {
                $size = self::syncsafe(substr($tag, $pos + 4, 4));
            } else {
                $size = unpack('N', substr($tag, $pos + 4, 4))[1];
            }

            $body = substr($tag, $pos + $headLen, $size);
            $pos += $headLen + $size;

            if (! isset($map[$id]) || $size <= 0) {
                continue;
            }

            $field = $map[$id];

            if ($field === 'cover') {
                $out['cover'] ??= self::parsePicture($body, $major === 2);
            } else {
                $text = self::decodeText(ord($body[0]), substr($body, 1));
                if ($field === 'genre') {
                    $text = trim(preg_replace('/^\(\d+\)/', '', $text));
                    $text = is_numeric($text) ? '' : $text;
                }
                if ($text !== '') {
                    $out[$field] ??= $text;
                }
            }
        }

        return $out;
    }

    protected static function parsePicture(string $body, bool $v22): ?array
    {
        $encoding = ord($body[0]);

        if ($v22) {
            $format = strtolower(substr($body, 1, 3));
            $mime = $format === 'png' ? 'image/png' : 'image/jpeg';
            $rest = substr($body, 5);
        } else {
            $end = strpos($body, "\0", 1);
            if ($end === false) {
                return null;
            }
            $mime = strtolower(substr($body, 1, $end - 1)) ?: 'image/jpeg';
            $rest = substr($body, $end + 2); // skip null + picture type
        }

        // Skip the description, terminated by one null (latin1/utf8) or two aligned nulls (utf16).
        if ($encoding === 1 || $encoding === 2) {
            $i = 0;
            while ($i + 1 < strlen($rest) && substr($rest, $i, 2) !== "\0\0") {
                $i += 2;
            }
            $data = substr($rest, $i + 2);
        } else {
            $i = strpos($rest, "\0");
            $data = $i === false ? '' : substr($rest, $i + 1);
        }

        if (strlen($data) < 100) {
            return null;
        }

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/'.($mime === 'png' ? 'png' : 'jpeg');
        }

        return ['data' => $data, 'mime' => $mime];
    }

    protected static function decodeText(int $encoding, string $raw): string
    {
        $text = match ($encoding) {
            1 => mb_convert_encoding($raw, 'UTF-8', 'UTF-16'),
            2 => mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE'),
            3 => $raw,
            default => self::toUtf8($raw),
        };

        // Multiple values are null separated; keep the first.
        $text = explode("\0", $text)[0];

        return trim(str_replace("\u{FEFF}", '', $text));
    }

    protected static function toUtf8(string $s): string
    {
        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    protected static function mp3Duration($fh, int $audioStart, int $audioEnd): ?int
    {
        fseek($fh, $audioStart);
        $buf = fread($fh, 128 * 1024);
        $len = strlen($buf);

        $bitrates = [
            1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
            2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        ];
        $rates = [3 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 0 => [11025, 12000, 8000]];

        for ($i = 0; $i + 4 < $len; $i++) {
            if (ord($buf[$i]) !== 0xFF || (ord($buf[$i + 1]) & 0xE0) !== 0xE0) {
                continue;
            }

            $b1 = ord($buf[$i + 1]);
            $b2 = ord($buf[$i + 2]);
            $b3 = ord($buf[$i + 3]);

            $versionBits = ($b1 >> 3) & 0x3;   // 3 = MPEG1, 2 = MPEG2, 0 = MPEG2.5
            $layerBits = ($b1 >> 1) & 0x3;     // 1 = Layer III
            $bitrateIdx = ($b2 >> 4) & 0xF;
            $rateIdx = ($b2 >> 2) & 0x3;

            if ($versionBits === 1 || $layerBits !== 1 || $bitrateIdx === 0 || $bitrateIdx === 15 || $rateIdx === 3) {
                continue;
            }

            $mpeg1 = $versionBits === 3;
            $sampleRate = $rates[$versionBits][$rateIdx];
            $bitrate = $bitrates[$mpeg1 ? 1 : 2][$bitrateIdx] * 1000;
            $samplesPerFrame = $mpeg1 ? 1152 : 576;
            $mono = (($b3 >> 6) & 0x3) === 3;

            // Xing / Info header carries the real frame count for VBR files.
            $xingOffset = $i + 4 + ($mpeg1 ? ($mono ? 17 : 32) : ($mono ? 9 : 17));
            $tag = substr($buf, $xingOffset, 4);
            if (($tag === 'Xing' || $tag === 'Info') && (ord($buf[$xingOffset + 7]) & 0x1)) {
                $frames = unpack('N', substr($buf, $xingOffset + 8, 4))[1];

                return (int) round($frames * $samplesPerFrame / $sampleRate);
            }

            if (substr($buf, $i + 36, 4) === 'VBRI') {
                $frames = unpack('N', substr($buf, $i + 36 + 14, 4))[1];

                return (int) round($frames * $samplesPerFrame / $sampleRate);
            }

            // Constant bitrate: size / rate.
            return (int) round(($audioEnd - ($audioStart + $i)) * 8 / $bitrate);
        }

        return null;
    }
}
