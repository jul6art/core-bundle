<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation;

/**
 * Finds the translation keys that JavaScript sources ask for.
 *
 * It reads `t(…)` and `trans(…)`, whatever the receiver — `this.t()` inside a Stimulus
 * controller, `c.t()` inside a datatable renderer, a bare `t()` imported from the registry.
 *
 * ## Why a scanner rather than a list
 *
 * A list of keys maintained by hand is a list that drifts: it is written once, and the day a
 * controller stops reading a key, nothing says so. Scanning the code means the guard covers a
 * controller written tomorrow without anyone remembering to enrol it.
 *
 * ## What it deliberately does not do
 *
 * It is not a JavaScript parser. It strips comments, then matches call sites — which is enough
 * for the shape this code base actually writes, and honest about the rest: a call it cannot read
 * is reported as dynamic rather than skipped.
 *
 * > ⚠️ One known limit: a regular expression literal carrying an unpaired quote (`/don't/`)
 * > would put the comment stripper into string mode for the rest of the file. No source in this
 * > ecosystem does that, and the failure would be loud (keys disappearing from the scan), not
 * > silent.
 */
final readonly class JsTranslationScanner
{
    /**
     * `(?<![\w$])` is the whole reason this class is not a one-line preg_match_all.
     *
     * ⚠️ `rows.at(`, `list.sort(`, `q.insert(` and `this.format(` all end with `t(`. Without the
     * anchor, every one of them is reported as a missing translation, the guard cries wolf on its
     * first run, and someone switches it off — which is worse than not having written it.
     */
    private const string CALL_PATTERN = '/(?<![\w$])(?:trans|t)\s*\(/';

    /**
     * @param list<string> $extensions          file extensions worth reading
     * @param list<string> $excludedDirectories directory names never walked into. ⚠️ Minified
     *                                          third-party bundles are fields of one-letter
     *                                          identifiers: scanning `assets/vendor/` reported
     *                                          sixteen dynamic calls in `cereezer` before this
     *                                          existed, none of them the project's own
     */
    public function __construct(
        private array $extensions = ['js', 'mjs', 'ts'],
        private array $excludedDirectories = ['node_modules', 'vendor', 'dist', 'build'],
    ) {
    }

    /**
     * Scans every JavaScript file below the given directories. A directory that does not exist
     * contributes nothing: a project that has no `assets/` yet is not a failure.
     */
    public function scan(string ...$directories): JsTranslationScan
    {
        $scan = new JsTranslationScan();

        foreach ($directories as $directory) {
            foreach ($this->filesIn($directory) as $file) {
                $contents = file_get_contents($file);

                if (false === $contents) {
                    continue;
                }

                $scan = $scan->merge($this->scanSource($contents, $file));
            }
        }

        return $scan;
    }

    /**
     * @param string $origin how the occurrences of this source are named in a report
     */
    public function scanSource(string $source, string $origin): JsTranslationScan
    {
        $code = self::withoutComments($source);

        /** @var array<string, list<string>> $literals */
        $literals = [];
        /** @var array<string, list<string>> $prefixes */
        $prefixes = [];
        /** @var list<string> $dynamic */
        $dynamic = [];

        preg_match_all(self::CALL_PATTERN, $code, $matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$matched, $offset]) {
            $argumentsAt = $offset + \strlen($matched);
            $argument = self::firstArgument($code, $argumentsAt);
            $location = \sprintf('%s:%d', $origin, self::lineAt($code, $offset));

            // `t(key) {` declares the method; it does not read a key. Left in, it would put a
            // permanent false line in every report — the mixin's own definition.
            if (null === $argument && self::isDeclaration($code, $argumentsAt)) {
                continue;
            }

            if (null === $argument) {
                $dynamic[] = $location;

                continue;
            }

            [$value, $isPrefix] = $argument;

            // A template literal whose constant head is empty promises nothing: filing
            // `` c.t(`${path}.${key}`) `` as a prefix would let it vouch for the whole catalogue.
            if ($isPrefix && '' === $value) {
                $dynamic[] = $location;

                continue;
            }

            if ($isPrefix) {
                $prefixes[$value][] = $location;

                continue;
            }

            $literals[$value][] = $location;
        }

        return new JsTranslationScan($literals, $prefixes, $dynamic);
    }

    /**
     * Reads the first argument of a call that starts at $offset.
     *
     * @return array{string, bool}|null the value and whether it is a prefix rather than a key;
     *                                  null when the argument is not a literal at all
     */
    private static function firstArgument(string $code, int $offset): ?array
    {
        $length = \strlen($code);

        while ($offset < $length && \in_array($code[$offset], [' ', "\t", "\n", "\r"], true)) {
            ++$offset;
        }

        if ($offset >= $length) {
            return null;
        }

        $quote = $code[$offset];

        if (!\in_array($quote, ["'", '"', '`'], true)) {
            return null;
        }

        $value = '';

        for ($i = $offset + 1; $i < $length; ++$i) {
            $char = $code[$i];

            if ('\\' === $char) {
                $value .= $code[$i + 1] ?? '';
                ++$i;

                continue;
            }

            if ($char === $quote) {
                // A template literal with an interpolation names no key, only the constant head
                // it promises: `datatable.modal.${type}.title` vouches for `datatable.modal.`.
                if ('`' === $quote && str_contains($value, '${')) {
                    return [substr($value, 0, (int) strpos($value, '${')), true];
                }

                return [$value, false];
            }

            $value .= $char;
        }

        // An unterminated literal: the file is broken, and guessing would invent a key.
        return null;
    }

    /**
     * Is this `t(` opening a *declaration* rather than a call?
     *
     * The shape is unmistakable: the matching closing parenthesis is followed by `{`. Nothing
     * calls a function and then opens a block.
     */
    private static function isDeclaration(string $code, int $offset): bool
    {
        $length = \strlen($code);
        $depth = 1;

        for ($i = $offset; $i < $length; ++$i) {
            $char = $code[$i];

            if (\in_array($char, ["'", '"', '`'], true)) {
                $i = self::endOfLiteral($code, $i);

                continue;
            }

            if ('(' === $char) {
                ++$depth;

                continue;
            }

            if (')' !== $char || 0 !== --$depth) {
                continue;
            }

            for ($j = $i + 1; $j < $length; ++$j) {
                if (!\in_array($code[$j], [' ', "\t", "\n", "\r"], true)) {
                    return '{' === $code[$j];
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @return int the offset of the closing quote, or the end of the string when unterminated
     */
    private static function endOfLiteral(string $code, int $offset): int
    {
        $quote = $code[$offset];
        $length = \strlen($code);

        for ($i = $offset + 1; $i < $length; ++$i) {
            if ('\\' === $code[$i]) {
                ++$i;

                continue;
            }

            if ($code[$i] === $quote) {
                return $i;
            }
        }

        return $length - 1;
    }

    /**
     * Blanks out comments while preserving offsets and line breaks, so the locations a report
     * prints still point at the right line.
     */
    private static function withoutComments(string $source): string
    {
        $length = \strlen($source);
        $out = '';
        $i = 0;

        while ($i < $length) {
            $char = $source[$i];
            $next = $source[$i + 1] ?? '';

            if ('/' === $char && '/' === $next) {
                while ($i < $length && "\n" !== $source[$i]) {
                    $out .= ' ';
                    ++$i;
                }

                continue;
            }

            if ('/' === $char && '*' === $next) {
                while ($i < $length && !('*' === $source[$i] && '/' === ($source[$i + 1] ?? ''))) {
                    $out .= "\n" === $source[$i] ? "\n" : ' ';
                    ++$i;
                }

                $out .= '  ';
                $i += 2;

                continue;
            }

            if (\in_array($char, ["'", '"', '`'], true)) {
                $out .= $char;
                ++$i;

                while ($i < $length) {
                    $out .= $source[$i];

                    if ('\\' === $source[$i]) {
                        $out .= $source[$i + 1] ?? '';
                        $i += 2;

                        continue;
                    }

                    if ($source[$i] === $char) {
                        ++$i;

                        break;
                    }

                    ++$i;
                }

                continue;
            }

            $out .= $char;
            ++$i;
        }

        return $out;
    }

    private static function lineAt(string $code, int $offset): int
    {
        return substr_count(substr($code, 0, $offset), "\n") + 1;
    }

    /**
     * @return list<string> absolute paths, sorted so two runs report the same order
     */
    private function filesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $excluded = $this->excludedDirectories;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $file): bool => !$file->isDir() || !\in_array($file->getFilename(), $excluded, true),
            ),
        );

        $files = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (\in_array($file->getExtension(), $this->extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
