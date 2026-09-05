<?php

declare(strict_types=1);

namespace Goat\Support;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * Modern terminal banner for GOAT.
 *
 * Renders `images/meeeh.png` on the left and an introduction card on the right
 * using true-color half-block ANSI art. Falls back gracefully when GD or colors
 * are unavailable or terminal is too narrow (stacked layout).
 *
 * Usage:
 *   GoatBanner::render($this->output, 'Product');
 *   GoatBanner::render($this->output); // intro / about
 */
final class GoatBanner
{
    private const IMAGE_PATH = __DIR__ . '/../../images/meeeh.png';

    // Palette - truecolor
    private const C_GOLD = [250, 204, 21];
    private const C_WHITE = [248, 250, 252];
    private const C_GRAY = [148, 163, 184];
    private const C_DIM = [100, 116, 139];
    private const C_CYAN = [14, 165, 233];
    private const C_GREEN = [34, 197, 94];
    private const C_PINK = [232, 121, 249];
    private const C_AMBER = [251, 146, 60];

    public static function render(OutputInterface $output, ?string $modelName = null, bool $compact = false, bool $withImage = true): void
    {
        // Allow disabling via env or config (useful for CI / quiet mode)
        if (getenv('GOAT_NO_BANNER') === '1' || getenv('GOAT_NO_BANNER') === 'true') {
            $output->writeln('');
            $output->writeln('  <fg=yellow>🐐 GOAT</> <fg=gray>v' . self::detectVersion() . ' — https://github.com/CodeWithTeds/meehh</>');
            $output->writeln('');
            return;
        }
        try {
            if (function_exists('config') && config('goat.banner') === false) {
                return;
            }
        } catch (\Throwable) {}

        $terminal = new Terminal();
        $width = $terminal->getWidth();
        if ($width <= 0) {
            $width = 100;
        }
        $isDecorated = $output->isDecorated();

        // Try inline image mode if explicitly enabled (GOAT_INLINE_IMAGE=1)
        // This prints the real PNG via iTerm2/Kitty protocol and then the text card.
        if ($withImage && $isDecorated && AsciiImageRenderer::tryInlineImage($output, self::IMAGE_PATH)) {
            $output->writeln('');
            self::renderTextCard($output, $modelName, $width, $compact);
            return;
        }

        // Decide layout
        $stacked = $width < 88 || ! $isDecorated || ! $withImage;

        // Pick image dimensions based on width
        [$targetW, $targetLines] = match (true) {
            $width >= 120 => [38, 18],
            $width >= 100 => [34, 16],
            $width >= 88  => [30, 14],
            default       => [26, 12],
        };

        if ($stacked) {
            $targetW = min(34, (int) ($width * 0.6));
            $targetLines = 12;
        }

        $imageLines = $withImage ? AsciiImageRenderer::render(self::IMAGE_PATH, $targetW, $targetLines) : [];

        // If output is not decorated, strip ANSI from image and just print plain fallback
        if (! $isDecorated) {
            $imageLines = array_map(fn ($l) => preg_replace('/\e\[[0-9;]*m/', '', $l) ?? $l, $imageLines);
        }

        $rightLines = self::buildRightLines($modelName, $compact, $isDecorated);

        // If image is disabled, just render text card
        if (! $withImage) {
            self::renderTextCard($output, $modelName, $width, $compact);
            return;
        }

        if ($stacked) {
            self::renderStacked($output, $imageLines, $rightLines, $width, $isDecorated);
        } else {
            self::renderSideBySide($output, $imageLines, $rightLines, $width, $isDecorated, $targetW, $targetLines);
        }
    }

    /**
     * Plain text card without image - used for inline-image mode or as fallback
     */
    private static function renderTextCard(OutputInterface $output, ?string $modelName, int $width, bool $compact): void
    {
        $lines = self::buildRightLines($modelName, $compact, $output->isDecorated());
        $cardWidth = min($width - 4, 84);
        $border = str_repeat('─', $cardWidth);

        $output->writeln(self::dim("  ╭{$border}╮"));
        foreach ($lines as $l) {
            $vis = self::visibleLength($l);
            $pad = max(0, $cardWidth - $vis);
            // l already contains ANSI, we pad after
            $output->writeln(self::dim('  │ ') . $l . str_repeat(' ', $pad) . self::dim(' │'));
        }
        $output->writeln(self::dim("  ╰{$border}╯"));
    }

    private static function renderStacked(OutputInterface $output, array $imageLines, array $rightLines, int $width, bool $decorated): void
    {
        $output->writeln('');

        // Center image
        foreach ($imageLines as $line) {
            $vis = self::visibleLength($line);
            $pad = max(0, (int) (($width - $vis) / 2));
            $output->writeln(str_repeat(' ', $pad) . $line);
        }

        $output->writeln('');
        $output->writeln(self::dim('  ' . str_repeat('─', min($width - 4, 70))));

        foreach ($rightLines as $line) {
            // strip leading spaces for stacked centered feel? Keep indent
            $output->writeln('  ' . $line);
        }

        $output->writeln(self::dim('  ' . str_repeat('─', min($width - 4, 70))));
        $output->writeln('');
    }

    private static function renderSideBySide(
        OutputInterface $output,
        array $imageLines,
        array $rightLines,
        int $width,
        bool $decorated,
        int $imageW,
        int $imageLinesCount
    ): void {
        $output->writeln('');

        // Normalize line counts: image and right should have same count
        $count = max(count($imageLines), count($rightLines));
        // Pad shorter
        $imageLines = array_pad($imageLines, $count, str_repeat(' ', $imageW));
        $rightLines = array_pad($rightLines, $count, '');

        // Decorative top rule with title - spans width
        $title = '  GOAT  ';
        $ruleLen = min($width - 6, 96);
        $rightRule = max(0, $ruleLen - mb_strlen($title) - 4);
        $topRule = self::dim('  ╭─' . $title . str_repeat('─', $rightRule) . '╮');
        // Only show top rule if width allows
        if ($width >= 80) {
            $output->writeln($topRule);
        }

        $gap = '   '; // 3 spaces between image and text
        $gapVis = 3;

        // For outer border style, prefix with │ and suffix with │
        $hasBorder = $width >= 80;

        for ($i = 0; $i < $count; $i++) {
            $left = $imageLines[$i] ?? str_repeat(' ', $imageW);
            $right = $rightLines[$i] ?? '';

            // Compute actual visible width of left (rtrim in renderer may shrink it)
            $leftVis = self::visibleLength($left);
            // Ensure right fits inside border: truncate if needed
            $maxRight = $hasBorder ? max(10, $ruleLen - $leftVis - $gapVis - 4) : 120;
            $right = self::truncateToVisible($right, $maxRight, $decorated);
            $rightVis = self::visibleLength($right);

            $lineInside = $left . $gap . $right;

            if ($hasBorder) {
                $insideVis = $leftVis + $gapVis + $rightVis;
                $insideWidth = $ruleLen; // inside between │ and │
                $pad = max(0, $insideWidth - $insideVis - 2); // -2 for spaces after │ and before │
                $output->writeln(
                    self::dim('  │ ') . $lineInside . str_repeat(' ', $pad) . self::dim(' │')
                );
            } else {
                $output->writeln('  ' . $lineInside);
            }
        }

        if ($hasBorder) {
            $bottomRule = self::dim('  ╰' . str_repeat('─', $ruleLen) . '╯');
            $output->writeln($bottomRule);
        }

        $output->writeln('');
    }

    /**
     * Build the right-hand introduction lines.
     *
     * @return string[] Each line already contains ANSI colors if $decorated
     */
    private static function buildRightLines(?string $modelName, bool $compact, bool $decorated): array
    {
        $version = self::detectVersion();
        $php = PHP_VERSION;
        // Shorten php version
        $phpShort = implode('.', array_slice(explode('.', $php), 0, 2));

        // Helpers
        $gold = fn(string $t, bool $bold = true): string => $decorated ? self::ansi($t, self::C_GOLD, $bold) : $t;
        $white = fn(string $t, bool $bold = true): string => $decorated ? self::ansi($t, self::C_WHITE, $bold) : $t;
        $gray = fn(string $t): string => $decorated ? self::ansi($t, self::C_GRAY, false) : $t;
        $dim = fn(string $t): string => $decorated ? self::ansi($t, self::C_DIM, false) : $t;
        $cyan = fn(string $t, bool $bold = false): string => $decorated ? self::ansi($t, self::C_CYAN, $bold) : $t;
        $green = fn(string $t): string => $decorated ? self::ansi($t, self::C_GREEN, false) : $t;
        $amber = fn(string $t): string => $decorated ? self::ansi($t, self::C_AMBER, false) : $t;

        $lines = [];

        // Title row
        $lines[] = $gold('🐐 GOAT', true) . '  ' . $dim('—') . '  ' . $white('Terminal-first Feature Generator', true) . '  ' . $dim('v' . $version);
        $lines[] = $gray('Template  ·  Boilerplate  ·  Feature Slice  —  from a single migration or ERD');

        if ($modelName !== null && $modelName !== '') {
            $lines[] = '';
            $lines[] = $cyan('▶  Generating: ', true) . $white($modelName, true) . '  ' . $dim('→  app/Models/' . $modelName . '.php  +  8 artifacts');
        } else {
            $lines[] = $dim('Paste a migration or ERD  →  get a production-ready slice in 2s');
        }

        $lines[] = '';
        $lines[] = $dim(str_repeat('─', 52));

        // Owner / link
        $lines[] = $gray('by ') . $white('Prof Alex Software Dev', true) . $dim('  /  ') . $cyan('TE-AD', true) . '   ' . $dim('↗') . '  ' . $cyan('github.com/CodeWithTeds/meehh');
        $lines[] = $dim('https://github.com/CodeWithTeds/meehh') . '   ' . $gray('MIT  •  Local-first  •  No AI');

        // Hero quote — single source of truth intro
        $lines[] = '';
        $lines[] = $gold('🐐 ', true) . $white('Your schema is already there. Why build', true);
        $lines[] = $white('around it manually when the structure', true) . ' ' . $gray('you’ve already defined');
        $lines[] = $gray('could be the starting point for everything that comes next?');

        if (! $compact) {
            $lines[] = '';
            $lines[] = $amber('✦  You give:') . '  ' . $gray("Schema::create('products', ...)") . $dim('  or  ') . $gray('ERD text');
            $lines[] = $green('▸  You get:') . '  ' . $white('Model  •  Requests  •  Resource  •  Controller') . $dim('  •');
            $lines[] = '         ' . $white('Service  •  Repository  •  Policy  •  Tests  •  Migration');
        }

        $lines[] = '';
        // Command hint - highlight primary command
        $cmd = 'php artisan goat:make ' . ($modelName ?: 'Product');
        $lines[] = $cyan('❯', true) . '  ' . $white($cmd, true) . '  ' . $dim('--from=migration  --from=erd  --force');
        $lines[] = $dim('   --only=model,resource  --except=policy,test') . '   ' . $gray('PHP ' . $phpShort . '  •  Laravel 11|12|13');

        // Footer tip for shell open case
        if ($modelName === null) {
            $lines[] = '';
            $lines[] = $dim('Tip: add ') . $gray('php artisan goat') . $dim(' to your shell startup to see this banner on open.');
        }

        return $lines;
    }

    private static function detectVersion(): string
    {
        static $ver = null;
        if ($ver !== null) return $ver;

        // Try composer.json
        $candidates = [
            __DIR__ . '/../../composer.json',
            dirname(__DIR__, 2) . '/composer.json',
            getcwd() . '/composer.json',
        ];
        foreach ($candidates as $p) {
            if (! file_exists($p)) continue;
            $json = @json_decode((string) file_get_contents($p), true);
            if (isset($json['version'])) {
                return $ver = (string) $json['version'];
            }
        }
        // Fallback to parsing README badge? hardcoded
        return $ver = '1.0.0';
    }

    private static function ansi(string $text, array $rgb, bool $bold = false): string
    {
        [$r,$g,$b] = $rgb;
        $bld = $bold ? '1;' : '';
        return "\e[{$bld}38;2;{$r};{$g};{$b}m{$text}\e[0m";
    }

    private static function dim(string $text): string
    {
        return "\e[38;2;100;116;139m{$text}\e[0m";
    }

    private static function truncateToVisible(string $line, int $max, bool $decorated): string
    {
        $vis = self::visibleLength($line);
        if ($vis <= $max) {
            return $line;
        }
        // Strip ANSI, truncate visible, then re-apply? Simpler: strip then truncate plain
        $plain = preg_replace('/\e\[[0-9;]*m/', '', $line) ?? $line;
        $plain = mb_substr($plain, 0, max(0, $max - 1)) . '…';
        // If decorated, we lose colors when truncating, but acceptable for overflow case.
        // Re-color dimly if needed: return plain without ANSI to avoid broken sequences.
        return $decorated ? self::ansi($plain, self::C_GRAY, false) : $plain;
    }

    private static function visibleLength(string $line): int
    {
        // Strip ANSI \e[...m  and  \e]1337... etc? Just ANSI SGR
        $stripped = preg_replace('/\e\[[0-9;]*m/', '', $line) ?? $line;
        // Strip OSC sequences
        $stripped = preg_replace('/\e\].*?\x07/', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\e\\].*?\a/', '', $stripped) ?? $stripped;
        // Also handle Symfony tags if any remain (should not)
        $stripped = preg_replace('/<[^>]+>/', '', $stripped) ?? $stripped;
        return mb_strlen($stripped);
    }
}
