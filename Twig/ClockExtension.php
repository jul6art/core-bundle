<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Twig;

use Jul6Art\CoreBundle\Util\Clock;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `clock_now()`: the current time from {@see Clock}, the clock a test can freeze. `'now'|date`
 * reads the system clock instead.
 *
 * ```twig
 * &copy; {{ clock_now()|date('Y') }}
 * {{ clock_now('+1 day')|date('d/m/Y') }}
 * ```
 */
final class ClockExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('clock_now', Clock::now(...)),
        ];
    }
}
