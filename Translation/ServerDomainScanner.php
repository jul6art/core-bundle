<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation;

/**
 * Finds the server-side callers that ask for a browser key in the wrong catalogue.
 *
 * ## The defect it exists to catch
 *
 * The `javascript` domain is one of TRANSPORT: a vocabulary read by the browser is MOVED there,
 * never copied — two copies of one label diverge on the first change. The move is the easy half.
 * The hard half is that the same vocabulary is often rendered server-side too: a status is a
 * badge in the datatable and a chip on the record page.
 *
 * ⚠️ A `|trans` left on the old domain does not fail. It renders the KEY, in full, in the page —
 * `sirh.expense.status.submitted` where the reader expects "Submitted". Twice in this ecosystem
 * a whole vocabulary shipped that way, and both times the screen tests found it, months later,
 * by looking for a French word in the HTML. The move ends with its callers, or it is not done.
 *
 * ## What counts as a caller, and what does not
 *
 * The scanner reads literals, not code. For each key it is given, it looks at what follows the
 * literal:
 *
 *  - a Twig `|trans` with no `javascript` in reach — the label goes out on the default domain;
 *  - an explicit second argument naming another domain — `$this->t('…', 'datatable')`.
 *
 * ⚠️ A literal followed by neither is NOT reported, and that is the point: an enum's
 * `label()` returns a key and nothing else, by design — the domain belongs to whoever renders
 * it. Reporting those would drown the real defect under every enum of the project.
 */
final readonly class ServerDomainScanner
{
    /** How far after the literal a domain argument may still be considered attached to it. */
    private const int LOOKAHEAD = 80;

    /**
     * @param list<string> $extensions          file extensions worth reading
     * @param list<string> $excludedDirectories directory names never walked into
     */
    public function __construct(
        private string $domain = 'javascript',
        private array $extensions = ['twig', 'php'],
        private array $excludedDirectories = ['node_modules', 'vendor', 'var', 'public'],
    ) {
    }

    /**
     * @param list<string> $keys        the keys only the browser domain holds
     * @param list<string> $directories where the server-side code lives
     *
     * @return list<string> one human-readable line per offending call site, sorted
     */
    public function scan(array $keys, array $directories): array
    {
        if ([] === $keys) {
            return [];
        }

        $offenders = [];

        foreach ($directories as $directory) {
            foreach ($this->filesIn($directory) as $file) {
                $source = (string) file_get_contents($file);

                foreach ($keys as $key) {
                    foreach ($this->offendingCalls($source, $key) as $line => $excerpt) {
                        $offenders[] = \sprintf('%s:%d  %s', $file, $line, $excerpt);
                    }
                }
            }
        }

        sort($offenders);

        return array_values(array_unique($offenders));
    }

    /**
     * @return array<int, string> line number => excerpt
     */
    private function offendingCalls(string $source, string $key): array
    {
        $found = [];

        foreach (["'".$key."'", '"'.$key.'"'] as $literal) {
            $offset = 0;

            while (false !== $at = strpos($source, $literal, $offset)) {
                $offset = $at + \strlen($literal);
                $after = substr($source, $offset, self::LOOKAHEAD);

                if ($this->isTranslatedElsewhere($after)) {
                    $line = substr_count($source, "\n", 0, $at) + 1;
                    $found[$line] = trim($this->lineAt($source, $at));
                }
            }
        }

        return $found;
    }

    /**
     * ⚠️ The domain is searched for in the WHOLE lookahead, not parsed out of it. A Twig call
     * spreads its arguments as `|trans({'%name%': …}, 'javascript')`, and a parser strict enough
     * to read that would be strict enough to miss the next shape someone writes.
     */
    private function isTranslatedElsewhere(string $after): bool
    {
        if (str_contains($after, $this->domain)) {
            return false;
        }

        // Twig: `'key'|trans` — with no domain in reach, it goes out on the default one.
        if (preg_match('/^\s*\|\s*trans\b/', $after)) {
            return true;
        }

        // PHP: a second argument naming a domain — `t('key', 'datatable')`, `trans('key', [], 'x')`.
        return 1 === preg_match('/^\s*,\s*(?:\[[^]]*\]\s*,\s*)?[\'"][a-z][\w.]*[\'"]\s*\)/i', $after);
    }

    private function lineAt(string $source, int $position): string
    {
        $start = strrpos(substr($source, 0, $position), "\n");
        $start = false === $start ? 0 : $start + 1;
        $end = strpos($source, "\n", $position);

        return substr($source, $start, (false === $end ? \strlen($source) : $end) - $start);
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                fn (\SplFileInfo $file): bool => !$file->isDir()
                    || !\in_array($file->getFilename(), $this->excludedDirectories, true),
            ),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            foreach ($this->extensions as $extension) {
                if (str_ends_with($file->getFilename(), '.'.$extension)) {
                    $files[] = $file->getPathname();

                    break;
                }
            }
        }

        sort($files);

        return $files;
    }
}
