<?php

declare(strict_types=1);

namespace Goat\Support;

/**
 * Converts images/meeeh.png (or any PNG) into true-color ANSI half-block art.
 *
 * Each terminal line renders TWO pixel rows using the `▄` half-block:
 *   background = top pixel, foreground = bottom pixel.
 *
 * Transparent pixels fall back to terminal background.
 */
final class AsciiImageRenderer
{
    private const FALLBACK_GOAT = [
        // 32 cols × 16 lines - handcrafted pixel-goat fallback when GD is unavailable
        // Uses fg colors via <fg=...> will be replaced by ANSI generation elsewhere.
        // We provide monochrome lines that still look good.
        '              ▄▄▄▄▄▄▄               ',
        '         ▄████▀▀▀▀▀▀████▄           ',
        '      ▄██▀  ▄▄▄▄▄▄▄▄▄  ▀██▄         ',
        '   ▄██▀  ▄██████████████▄  ▀██▄     ',
        '  █▀  ▄██▀  ████████████▀█▄   ▀█    ',
        ' █  ▄█▀  ▄█▀  ▀██████▀  ▀█▀█▄   █   ',
        '█  █▀  ▄█▀  ▄███████▀█▄   ▀█▀█  █   ',
        '█ █  ▄█▀  ▄█▀   ███   ▀█▄  ▀█▀█ █   ',
        '█ █  █▀  █▀  ████████  ▀█  ▀█▀█ █   ',
        '█ █  █  █  █▀  ████  ▀█  █  █▀█ █   ',
        '█ █  █  █ █  ▄██████▄  █  █ █▀█ █   ',
        '█ █  ▀█ █▀ ▄█▀ ▀▀▀▀ ▀█▄ ▀█ █▀ █ █   ',
        '▀█▄   ▀█▀ ▄█▀  ▀██▀  ▀█▄ ▀█▀  ▄█▀    ',
        '  ▀█▄   ▀█▀  ▄██████▄  ▀█▀  ▄█▀      ',
        '    ▀██▄   ▀█▀  ▀▀▀▀  ▀█▀  ▄██▀       ',
        '       ▀▀▀▄▄▄▄▄▄▄▄▄▄▄▄▄▄▀▀▀          ',
    ];

    /**
     * Render PNG to ANSI lines.
     *
     * @return string[] Each entry is one terminal line (with ANSI 24-bit colors + reset)
     */
    public static function render(string $imagePath, int $targetWidth = 36, int $targetLines = 16): array
    {
        if (! file_exists($imagePath) || ! extension_loaded('gd')) {
            return self::fallback($targetWidth);
        }

        $src = @imagecreatefrompng($imagePath);
        if ($src === false) {
            return self::fallback($targetWidth);
        }

        // Normalize source to truecolor with alpha
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $dstW = $targetWidth;
        $dstH = $targetLines * 2; // two pixel rows per terminal line

        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);
            return self::fallback($targetWidth);
        }

        // Preserve transparency
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        $lines = [];
        for ($y = 0; $y < $targetLines; $y++) {
            $line = '';
            $pyTop = $y * 2;
            $pyBot = $y * 2 + 1;

            for ($x = 0; $x < $dstW; $x++) {
                $topInt = imagecolorat($dst, $x, $pyTop);
                $botInt = imagecolorat($dst, $x, $pyBot);

                [$tr, $tg, $tb, $ta] = self::decodeColor($topInt);
                [$br, $bg, $bb, $ba] = self::decodeColor($botInt);

                $topTransparent = $ta >= 100; // GD alpha 0=opaque 127=transparent; treat >100 as transparent
                $botTransparent = $ba >= 100;

                if ($topTransparent && $botTransparent) {
                    $line .= ' ';
                    continue;
                }

                if ($topTransparent && ! $botTransparent) {
                    // only bottom visible -> bottom half block
                    $line .= sprintf("\e[38;2;%d;%d;%dm▄\e[0m", $br, $bg, $bb);
                    continue;
                }

                if (! $topTransparent && $botTransparent) {
                    // only top visible -> upper half block
                    $line .= sprintf("\e[38;2;%d;%d;%dm▀\e[0m", $tr, $tg, $tb);
                    continue;
                }

                // both opaque -> combine
                $line .= sprintf("\e[38;2;%d;%d;%d;48;2;%d;%d;%dm▄\e[0m", $br, $bg, $bb, $tr, $tg, $tb);
            }

            // Trim trailing spaces but keep ANSI resets
            $lines[] = rtrim($line) ;
        }

        imagedestroy($dst);

        // If rendering produced empty lines, fallback
        $nonEmpty = count(array_filter($lines, fn ($l) => trim(strip_tags($l)) !== '' || str_contains($l, "\e[")));
        if ($nonEmpty < 4) {
            return self::fallback($targetWidth);
        }

        return $lines;
    }

    /**
     * Try to output the PNG inline for terminals that support Kitty / iTerm2 graphics.
     * Returns true if inline image was written (caller should skip ASCII).
     */
    public static function tryInlineImage(object $output, string $imagePath, int $cellWidth = 36): bool
    {
        if (! file_exists($imagePath) || ! is_readable($imagePath)) {
            return false;
        }

        $term = (string) (getenv('TERM_PROGRAM') ?: '');
        $isKitty = isset($_ENV['KITTY_WINDOW_ID']) || str_contains($term, 'kitty');
        $isITerm = $term === 'iTerm.app' || getenv('ITERM_SESSION_ID') !== false;
        $isWezTerm = str_contains($term, 'WezTerm') || getenv('WEZTERM_PANE') !== false;

        // Only attempt inline if terminal is known to support it, or if user force enables via env GOAT_INLINE_IMAGE
        $force = getenv('GOAT_INLINE_IMAGE') === '1';
        if (! $force && ! $isKitty && ! $isITerm && ! $isWezTerm) {
            return false;
        }

        try {
            $data = base64_encode((string) file_get_contents($imagePath));
            if ($data === '' || $data === false) {
                return false;
            }

            // Kitty graphics protocol is most universal; iTerm also understands OSC 1337
            // We try Kitty first (works in Kitty, WezTerm), fallback to iTerm.
            if ($isKitty || $isWezTerm || $force) {
                // Kitty: ESC _G a=T,f=100,w=36,m=0;BASE64 ESC \
                // Simplified: transmit in one chunk (image is ~1.5MB, but base64 ~2MB - too large for one chunk)
                // So skip Kitty for large files and use iTerm instead
                if (strlen($data) > 800_000) {
                    // fall through to iTerm
                } else {
                    $output->writeln("\e_Ga=T,f=100,m=1;{$data}\e\\");
                    return true;
                }
            }

            if ($isITerm || $force || $isWezTerm) {
                // iTerm2 inline: ESC ]1337;File=inline=1;width=auto;height=10;preserveAspectRatio=1:BASE64 BEL
                $width = $cellWidth * 8; // approx pixels
                // Use height to keep aspect; iTerm will scale
                $output->writeln("\e]1337;File=inline=1;preserveAspectRatio=1;width={$width}px:{$data}\a");
                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function fallback(int $targetWidth): array
    {
        // Use fallback art, pad to targetWidth centering
        $lines = [];
        foreach (self::FALLBACK_GOAT as $raw) {
            $pad = max(0, (int) (($targetWidth - mb_strlen($raw)) / 2));
            // Colorize fallback with a warm palette using ANSI 256? Use truecolor for horns/face
            // Simple: horns brown, face cream - we approximate with \e[38;2
            $colored = self::colorizeFallbackLine($raw);
            $lines[] = str_repeat(' ', $pad) . $colored;
        }
        return $lines;
    }

    private static function colorizeFallbackLine(string $line): string
    {
        // Map characters to subtle colors: █/▓ for horns etc.
        // Keep simple single color cream (222,184,135) for goat face, brown (120,85,55) for horns
        // For fallback we just use one tone - enough for visibility
        // Use border-like effect: not per-char, just whole line in goat palette
        $hasBlock = str_contains($line, '█') || str_contains($line, '▄') || str_contains($line, '▀');
        if (! $hasBlock) {
            return $line;
        }
        // Horns brown 140,98,68  / face 245,224,196
        // Decide per line: top 4 lines horns brown, rest face
        // Caller will handle per-line; we just apply a warm tone
        return "\e[38;2;212;190;150m" . $line . "\e[0m";
    }

    /**
     * @return array{int,int,int,int} [r,g,b,a]
     */
    private static function decodeColor(int $color): array
    {
        $a = ($color >> 24) & 0xFF;
        // GD alpha 0-127 is stored in 7 bits; but PHP returns 0-127 in high bits shifted?
        // Normalize: if a > 127, divide?
        // Actually imagecolorat returns 0x7FRRGGBB when alpha present? For truecolor, alpha 0-127 left shifted 24.
        // So extract directly.
        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;

        // GD uses 0 opaque ... 127 transparent, but some PNG decodes give 0-127 in 7 bits
        // If a is >127 due to sign bit, fix
        if ($a < 0) { $a = 0; }
        if ($a > 127) { $a = (int) ($a / 2); }

        return [$r, $g, $b, $a];
    }

    public static function supportsTrueColor(): bool
    {
        $colorterm = (string) (getenv('COLORTERM') ?: '');
        if (str_contains(strtolower($colorterm), 'truecolor') || str_contains(strtolower($colorterm), '24bit')) {
            return true;
        }
        $term = (string) (getenv('TERM') ?: '');
        return str_contains($term, '256color') || str_contains($term, 'truecolor');
    }
}
