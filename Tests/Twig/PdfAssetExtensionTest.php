<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Twig;

use Jul6Art\CoreBundle\Twig\PdfAssetExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

/**
 * dompdf does not fetch remote URLs in production, and it has no base to resolve a
 * schemeless relative one — so `asset()` silently yields no image in a PDF. These two helpers
 * are the way around it.
 */
#[CoversClass(PdfAssetExtension::class)]
final class PdfAssetExtensionTest extends TestCase
{
    private string $publicDir;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/jul6art-pdf-'.bin2hex(random_bytes(6));
        mkdir($this->publicDir.'/img', 0o777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ((array) glob($this->publicDir.'/img/*') as $file) {
            if (\is_string($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->publicDir.'/img');
        @rmdir($this->publicDir);

        parent::tearDown();
    }

    public function testItResolvesARelativePathToAFilesystemPath(): void
    {
        self::assertSame($this->publicDir.'/img/logo.png', $this->extension()->resolve('img/logo.png'));
    }

    /** Templates store the path either way; both must resolve identically. */
    public function testALeadingSlashMakesNoDifference(): void
    {
        self::assertSame(
            $this->extension()->resolve('img/logo.png'),
            $this->extension()->resolve('/img/logo.png')
        );
    }

    /** Returning null lets the template keep its `{% if %}` unchanged. */
    public function testAnEmptyPathResolvesToNull(): void
    {
        self::assertNull($this->extension()->resolve(null));
        self::assertNull($this->extension()->resolve(''));
    }

    public function testResolveDoesNotRequireTheFileToExist(): void
    {
        self::assertSame($this->publicDir.'/nope.png', $this->extension()->resolve('nope.png'));
    }

    // ── data: URI ─────────────────────────────────────────────────────────

    public function testItEmbedsAnExistingImageAsADataUri(): void
    {
        $this->writeImage('img/logo.png', self::pngBytes());

        $uri = $this->extension()->dataUri('img/logo.png');

        self::assertIsString($uri);
        self::assertStringStartsWith('data:image/png;base64,', $uri);
        self::assertSame(self::pngBytes(), base64_decode(substr($uri, \strlen('data:image/png;base64,')), true));
    }

    public function testAMissingFileYieldsNullRatherThanABrokenUri(): void
    {
        self::assertNull($this->extension()->dataUri('img/absent.png'));
    }

    /**
     * A truncated upload would produce a valid-looking data URI that dompdf renders as a white
     * square — worse than no image, because nothing signals the failure.
     */
    public function testAFileTooSmallToBeAnImageIsRefused(): void
    {
        $this->writeImage('img/truncated.png', str_repeat('x', 99));

        self::assertNull($this->extension()->dataUri('img/truncated.png'));
    }

    public function testAnEmptyPathYieldsNoDataUri(): void
    {
        self::assertNull($this->extension()->dataUri(null));
        self::assertNull($this->extension()->dataUri(''));
    }

    public function testBothHelpersAreExposedToTemplates(): void
    {
        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $this->extension()->getFunctions()
        );

        self::assertContains('pdf_image_path', $names);
        self::assertContains('pdf_image_data_uri', $names);
    }

    private function extension(): PdfAssetExtension
    {
        return new PdfAssetExtension($this->publicDir);
    }

    private function writeImage(string $relative, string $bytes): void
    {
        file_put_contents($this->publicDir.'/'.$relative, $bytes);
    }

    /**
     * A structurally valid 1x1 PNG, padded past the size guard. The signature alone is not
     * enough: mime_content_type() reads the header, and a file with no IHDR is reported as
     * application/octet-stream — which is exactly what the first version of this fixture did.
     */
    private static function pngBytes(): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/AH3sYJmAAAAAElFTkSuQmCC',
            true
        );

        self::assertIsString($png);

        // Trailing bytes do not affect magic-byte detection, they only get the file past the
        // 100-byte floor the extension enforces.
        return $png.str_repeat("\x00", 100);
    }
}
