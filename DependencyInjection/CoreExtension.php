<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\DependencyInjection;

use Monolog\Formatter\HtmlFormatter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class CoreExtension.
 *
 * @phpstan-type CoreConfig array{
 *     email_debug: bool,
 *     email_debug_from: string|null,
 *     email_debug_title: string|null,
 *     email_debug_to: string|null,
 * }
 */
class CoreExtension extends Extension implements PrependExtensionInterface
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');
    }

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->resolveConfig($container);

        foreach ($config as $key => $parameter) {
            $container->setParameter(\sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }

        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['MonologBundle'])) {
            return;
        }

        if ('prod' === $container->getParameter('kernel.environment')) {
            $container->prependExtensionConfig('monolog', ['handlers' => $this->buildProdHandlers()]);
        }

        if (true === $config['email_debug']) {
            $container->prependExtensionConfig('monolog', [
                'handlers' => $this->buildEmailDebugHandlers($container, $config),
            ]);
        }
    }

    /**
     * Normalises the processed configuration into a shape the rest of the class can
     * rely on: Symfony's config layer only guarantees an untyped array.
     *
     * @return CoreConfig
     */
    private function resolveConfig(ContainerBuilder $container): array
    {
        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        $config = $this->processConfiguration(new Configuration(), \is_array($configs) ? $configs : []);

        return [
            'email_debug' => true === ($config['email_debug'] ?? false),
            'email_debug_from' => self::asStringOrNull($config['email_debug_from'] ?? null),
            'email_debug_title' => self::asStringOrNull($config['email_debug_title'] ?? null),
            'email_debug_to' => self::asStringOrNull($config['email_debug_to'] ?? null),
        ];
    }

    private static function asStringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildProdHandlers(): array
    {
        return [
            'console' => [
                'channels' => [
                    '!event',
                    '!doctrine',
                ],
                'type' => 'console',
                'process_psr_3_messages' => false,
            ],
            'login' => [
                'channels' => 'security',
                'level' => 'info',
                'path' => '%kernel.logs_dir%/auth.log',
                'type' => 'stream',
            ],
            'main' => [
                'action_level' => 'error',
                'channels' => [
                    '!php',
                ],
                'handler' => 'nested',
                'type' => 'fingers_crossed',
            ],
            'nested' => [
                'level' => 'info',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'type' => 'stream',
            ],
            'php' => [
                'channels' => [
                    'php',
                ],
                'level' => 'warning',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'type' => 'stream',
            ],
        ];
    }

    /**
     * @param CoreConfig $config
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws \InvalidArgumentException if the email debug settings are incomplete
     */
    private function buildEmailDebugHandlers(ContainerBuilder $container, array $config): array
    {
        $from = $config['email_debug_from'];
        $subject = $config['email_debug_title'];
        $to = $config['email_debug_to'];

        if (null === $from || false === filter_var($from, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('Parameter "%s.email_debug_from" must be a valid email address to activate email debug.', $this->getAlias()));
        }

        if (null === $subject || '' === $subject) {
            throw new \InvalidArgumentException(\sprintf('Parameter "%s.email_debug_title" must be configured to activate email debug.', $this->getAlias()));
        }

        if (null === $to || false === filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('Parameter "%s.email_debug_to" must be a valid email address to activate email debug.', $this->getAlias()));
        }

        // Monolog expects a service id here, and MailerHandler only sends an HTML
        // body when its formatter is an HtmlFormatter instance.
        $formatterId = \sprintf('%s.monolog.html_formatter', $this->getAlias());
        $container->register($formatterId, HtmlFormatter::class);

        return [
            'symfony_mailer' => [
                'formatter' => $formatterId,
                'from_email' => $from,
                'level' => 'critical',
                'subject' => $subject,
                'to_email' => $to,
                'type' => 'symfony_mailer',
            ],
            'deduplicated' => [
                'handler' => 'symfony_mailer',
                'type' => 'deduplication',
            ],
        ];
    }
}
