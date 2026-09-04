<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation;

/**
 * What a scan of the JavaScript sources found: the keys it can name, the prefixes it can only
 * promise, and the calls it could not read at all.
 *
 * The three lists exist because a scanner that reported only the first one would be lying. A
 * template literal (`` t(`datatable.modal.${type}.title`) ``) names no key, yet the catalogue
 * entries below it are alive; a variable (`t(labelKey)`) names nothing at all, and that is worth
 * saying out loud rather than passing over in silence.
 *
 * A location is a "path:line" string.
 *
 * @phpstan-type Occurrence string
 */
final readonly class JsTranslationScan
{
    /**
     * @param array<string, list<Occurrence>> $literals key => where it is read
     * @param array<string, list<Occurrence>> $prefixes constant head of a template literal => where
     * @param list<Occurrence>                $dynamic  calls whose argument is not a literal
     */
    public function __construct(
        private array $literals = [],
        private array $prefixes = [],
        private array $dynamic = [],
    ) {
    }

    /**
     * @return list<string> sorted, so a failure message reads the same twice
     */
    public function keys(): array
    {
        $keys = array_keys($this->literals);
        sort($keys);

        return $keys;
    }

    /**
     * @return list<Occurrence>
     */
    public function occurrences(string $key): array
    {
        return $this->literals[$key] ?? [];
    }

    /**
     * @return list<string> sorted
     */
    public function prefixes(): array
    {
        $prefixes = array_keys($this->prefixes);
        sort($prefixes);

        return $prefixes;
    }

    /**
     * @return list<Occurrence>
     */
    public function dynamicCalls(): array
    {
        return $this->dynamic;
    }

    /**
     * Is this catalogue key read by the code — by name, or through a prefix?
     *
     * Used to tell a dead entry from one that is only ever reached at runtime. A prefix vouches
     * for everything below it, which is deliberately generous: the cost of keeping a key that
     * nothing reads is one line in a YAML file, the cost of deleting a key that something reads
     * is a raw key on someone's screen.
     */
    public function covers(string $key): bool
    {
        if (isset($this->literals[$key])) {
            return true;
        }

        foreach (array_keys($this->prefixes) as $prefix) {
            if ('' !== $prefix && str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deep-merges another scan into this one, so several directories make one result.
     */
    public function merge(self $other): self
    {
        $literals = $this->literals;
        $prefixes = $this->prefixes;

        foreach ($other->literals as $key => $occurrences) {
            $literals[$key] = [...$literals[$key] ?? [], ...$occurrences];
        }

        foreach ($other->prefixes as $prefix => $occurrences) {
            $prefixes[$prefix] = [...$prefixes[$prefix] ?? [], ...$occurrences];
        }

        return new self($literals, $prefixes, [...$this->dynamic, ...$other->dynamic]);
    }
}
