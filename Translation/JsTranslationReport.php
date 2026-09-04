<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation;

/**
 * The outcome of a {@see JsTranslationAudit}: what is missing, what is dead, what could not be
 * read.
 *
 * ⚠️ Only the first two decide {@see self::isClean()}. Dynamic calls are reported because hiding
 * them would be dishonest, not because they are defects — `this.t(labelKey)` is legitimate code,
 * and a guard that failed the build over it is a guard someone switches off.
 */
final readonly class JsTranslationReport
{
    /**
     * @param array<string, list<string>> $missing key => locales whose catalogue does not define it
     * @param list<string>                $unused  keys of the domain that nothing reads
     * @param list<string>                $dynamic call sites whose key could not be read
     */
    public function __construct(
        private array $missing,
        private array $unused,
        private array $dynamic,
        private JsTranslationScan $scan,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function missing(): array
    {
        return $this->missing;
    }

    /**
     * @return list<string>
     */
    public function unused(): array
    {
        return $this->unused;
    }

    /**
     * @return list<string>
     */
    public function dynamicCalls(): array
    {
        return $this->dynamic;
    }

    public function isClean(): bool
    {
        return [] === $this->missing && [] === $this->unused;
    }

    /**
     * A failure message that says where to go.
     *
     * A key without its call site sends the reader grepping; the guard knows the file and the
     * line, so it prints them.
     */
    public function describe(): string
    {
        $lines = [];

        if ([] !== $this->missing) {
            $lines[] = \sprintf('%d key(s) read by JavaScript are absent from the catalogue:', \count($this->missing));

            foreach ($this->missing as $key => $locales) {
                $lines[] = \sprintf(
                    '  - %s (missing in %s) — read at %s',
                    $key,
                    implode(', ', $locales),
                    implode(', ', $this->scan->occurrences($key)) ?: 'declared by the test case',
                );
            }
        }

        if ([] !== $this->unused) {
            $lines[] = \sprintf('%d key(s) of the domain are read by nothing:', \count($this->unused));

            foreach ($this->unused as $key) {
                $lines[] = '  - '.$key;
            }
        }

        return implode("\n", $lines);
    }
}
