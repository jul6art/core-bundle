<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Logger;

/**
 * What the query-string redaction hides, and where it runs among the processors — readable WITHOUT
 * Monolog.
 *
 * ⚠️ **Why these values do not live on {@see QueryStringRedactingProcessor} alone.** The configuration
 * tree and the extension read them on every boot, Monolog or not, and the processor implements a
 * Monolog interface: reading one of ITS constants loads the class, hence the interface. From 3.1.0 to
 * 3.4.0 that is what they did, and every consumer without Monolog — which this bundle only suggests —
 * died on "Interface Monolog\Processor\ProcessorInterface not found" before its kernel finished booting.
 */
final class QueryStringRedaction
{
    /** @var list<string> */
    public const array DEFAULT_PARAMETERS = ['_hash', 'token', '_token', 'q', 'search'];

    /**
     * ⚠️ **Low, so the processor runs LAST among the logger's processors**: one that copies the URI into
     * the record (`WebProcessor`'s `url` and `referrer`) must run before it, or the copy stays in clear.
     * Handler-level processors still run after it — they are for the project to order.
     */
    public const int PRIORITY = -1024;
}
