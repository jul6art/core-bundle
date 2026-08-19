<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Makes a stored image path usable inside a PDF.
 *
 * Twig's `asset()` yields an HTTP URL relative to the current request. dompdf does not fetch
 * remote URLs in production (`isRemoteEnabled = false`) and has no base to resolve a
 * schemeless relative one — so the image simply never loads, silently.
 *
 * ```twig
 * {# a filesystem path, when dompdf is allowed to read the directory #}
 * <img src="{{ pdf_image_path(organization.logoPath) }}">
 *
 * {# a base64 data: URI, which no chroot or isRemoteEnabled setting can block #}
 * <img src="{{ pdf_image_data_uri(organization.logoPath) }}">
 * ```
 *
 * Prefer the data URI for small images — logos, headers, footers — accepting roughly a third
 * more HTML weight; prefer the path when a filesystem location is what is wanted.
 *
 * Both return `null` on an empty input, so a template keeps its `{% if %}` unchanged.
 */
final class PdfAssetExtension extends AbstractExtension
{
    /** Below this, a file cannot be a usable image — see {@see self::dataUri()}. */
    private const int MINIMUM_IMAGE_BYTES = 100;

    public function __construct(
        private readonly string $publicDir,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pdf_image_path', $this->resolve(...)),
            new TwigFunction('pdf_image_data_uri', $this->dataUri(...)),
        ];
    }

    /**
     * Absolute filesystem path of an image stored under the public directory. The file is not
     * required to exist: a template may well be pointing at a logo that was never uploaded.
     */
    public function resolve(?string $relativePath): ?string
    {
        if (null === $relativePath || '' === $relativePath) {
            return null;
        }

        return $this->publicDir.'/'.ltrim($relativePath, '/');
    }

    /**
     * Base64 `data:` URI of an image stored under the public directory.
     *
     * A file smaller than {@see self::MINIMUM_IMAGE_BYTES} is refused: a truncated upload would
     * produce a well-formed URI that dompdf renders as a **white square**, which is worse than
     * no image because nothing signals the failure.
     */
    public function dataUri(?string $relativePath): ?string
    {
        $full = $this->resolve($relativePath);

        if (null === $full || !is_file($full)) {
            return null;
        }

        $size = @filesize($full);

        if (false === $size || $size < self::MINIMUM_IMAGE_BYTES) {
            return null;
        }

        $bytes = @file_get_contents($full);

        if (false === $bytes || '' === $bytes) {
            return null;
        }

        $mime = \function_exists('mime_content_type') ? @mime_content_type($full) : false;

        return 'data:'.(\is_string($mime) && '' !== $mime ? $mime : 'image/jpeg').';base64,'.base64_encode($bytes);
    }
}
