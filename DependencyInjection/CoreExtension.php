<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\DependencyInjection;

use Jul6Art\CoreBundle\Command\PurgeCommand;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedTypeRegistrar;
use Jul6Art\CoreBundle\EventListener\SecurityHeaderListener;
use Jul6Art\CoreBundle\Security\Encryptor;
use Jul6Art\CoreBundle\Security\MathCaptchaService;
use Jul6Art\CoreBundle\Service\CascadeSoftDeleteHelper;
use Monolog\Formatter\HtmlFormatter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;

/**
 * Class CoreExtension.
 *
 * @phpstan-type CoreConfig array{
 *     email_debug: bool,
 *     email_debug_from: string|null,
 *     email_debug_title: string|null,
 *     email_debug_to: string|null,
 * }
 *
 * `encryption_key` is intentionally absent from CoreConfig: prepend() turns every key of
 * that shape into a container parameter, and the key must never be exposed that way. It is
 * read in load() only.
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

        // Deliberately the *unprocessed* configuration: an `%env(...)%` placeholder must
        // reach the service argument untouched so the secret is read at runtime instead of
        // being baked into the compiled container.
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerEncryption($container, \is_string($config['encryption_key'] ?? null) ? $config['encryption_key'] : null);
        $this->registerSecurityHeaders($container, \is_array($config['security_headers'] ?? null) ? $config['security_headers'] : []);
        $this->registerCaptcha($container, \is_array($config['captcha'] ?? null) ? $config['captcha'] : []);
        $this->registerDoctrineServices($container, self::purgeBatchSize($config), self::purgeAliases($config));
    }

    /**
     * Registered only when switched on: a listener that exists to do nothing on every response
     * is noise in the container, and the headers must never appear unasked.
     *
     * @param array<mixed> $config
     */
    private function registerSecurityHeaders(ContainerBuilder $container, array $config): void
    {
        if (true !== ($config['enabled'] ?? false)) {
            return;
        }

        $headers = $config['headers'] ?? [];

        $container->register(SecurityHeaderListener::class, SecurityHeaderListener::class)
            ->setArguments([
                true,
                // Passed through untouched so an `%env(bool:…)%` placeholder survives to the
                // container and is resolved at runtime; the constructor's bool type does the
                // casting. Coercing it here would turn the placeholder string into `true`.
                $config['csp_enforce'] ?? false,
                \is_string($config['csp_policy'] ?? null) ? $config['csp_policy'] : null,
                \is_array($headers) ? $headers : [],
            ])
            // Priority -100: run after the controllers and the other listeners have set their
            // own headers, since this one only fills the gaps.
            ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => -100]);
    }

    /**
     * @param array<mixed> $config
     */
    private function registerCaptcha(ContainerBuilder $container, array $config): void
    {
        $operations = $config['operations'] ?? ['+'];
        $sessionKey = $config['session_key'] ?? '_math_captcha_answer';

        $container->register(MathCaptchaService::class, MathCaptchaService::class)
            ->setArguments([
                new Reference('request_stack'),
                \is_array($operations) ? array_values($operations) : ['+'],
                \is_string($sessionKey) ? $sessionKey : '_math_captcha_answer',
            ]);
    }

    /**
     * The encryption bricks are opt-in: without a key there is nothing to register, and
     * registering them anyway would make every application boot fail on a missing env var
     * just because the bundle is installed.
     */
    private function registerEncryption(ContainerBuilder $container, ?string $encryptionKey): void
    {
        if (null === $encryptionKey || '' === $encryptionKey) {
            return;
        }

        $container->register(Encryptor::class, Encryptor::class)
            ->setArguments([$encryptionKey]);

        // String event names on purpose: referencing ConsoleEvents::COMMAND would make the
        // listener unloadable in an application without symfony/console, which this bundle
        // does not require.
        $container->register(EncryptedTypeRegistrar::class, EncryptedTypeRegistrar::class)
            ->setArguments([new Reference(Encryptor::class)])
            ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'register', 'priority' => 4096])
            ->addTag('kernel.event_listener', ['event' => 'console.command', 'method' => 'register', 'priority' => 4096]);
    }

    /**
     * Registered only when DoctrineBundle is enabled: `doctrine.orm.entity_manager` does
     * not exist otherwise, and an unresolvable reference would break the container of every
     * application that installs this bundle without the ORM.
     */
    /**
     * Reads the only purge setting the container needs, so the raw config array never has to
     * travel further than this class.
     *
     * @param array<mixed> $config
     */
    private static function purgeBatchSize(array $config): int
    {
        $purge = $config['purge'] ?? null;
        $batchSize = \is_array($purge) ? ($purge['batch_size'] ?? null) : null;

        return \is_int($batchSize) && $batchSize > 0 ? $batchSize : 100;
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<string>
     */
    private static function purgeAliases(array $config): array
    {
        $purge = $config['purge'] ?? null;
        $aliases = \is_array($purge) ? ($purge['aliases'] ?? []) : [];

        return \is_array($aliases) ? array_values(array_filter($aliases, \is_string(...))) : [];
    }

    /**
     * @param list<string> $purgeAliases
     */
    private function registerDoctrineServices(ContainerBuilder $container, int $purgeBatchSize, array $purgeAliases): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['DoctrineBundle'])) {
            return;
        }

        $container->register(CascadeSoftDeleteHelper::class, CascadeSoftDeleteHelper::class)
            ->setArguments([new Reference('doctrine.orm.entity_manager')]);

        $this->registerPurgeCommand($container, $purgeBatchSize, $purgeAliases);
    }

    /**
     * The purge needs symfony/console for the command itself and symfony/lock for the guard
     * that stops two runs racing on the same rows. Both are suggestions, not requirements, so
     * the command only exists when they do — and {@see PurgeCommandPass} drops it again if the
     * lock package is installed but `framework.lock` was never configured.
     *
     * @param list<string> $aliases
     */
    private function registerPurgeCommand(ContainerBuilder $container, int $batchSize, array $aliases): void
    {
        if (!class_exists(Command::class) || !class_exists(LockFactory::class)) {
            return;
        }

        // Aliases travel in the tag value, pipe-separated, the way #[AsCommand(name: 'a|b')]
        // expresses them: a lazily-registered command is never instantiated at compile time,
        // so a setAliases() call would come too late for the console to know the extra names.
        $container->register(PurgeCommand::class, PurgeCommand::class)
            ->setArguments([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('lock.factory'),
                new Reference('event_dispatcher'),
                $batchSize,
            ])
            ->addTag('console.command', ['command' => implode('|', ['core:purge', ...$aliases])]);
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

        // Merged by hand rather than through processConfiguration(): prepend() runs before the
        // container has turned `%env(...)%` strings into placeholders, so validating the whole
        // tree here would reject a perfectly legal `%env(bool:FOO)%` on any typed node. Only
        // the email_debug keys concern this method, and load() validates everything properly.
        $config = [];

        foreach (\is_array($configs) ? $configs : [] as $candidate) {
            if (\is_array($candidate)) {
                $config = [...$config, ...$candidate];
            }
        }

        return [
            'email_debug' => true === ($config['email_debug'] ?? false),
            'email_debug_from' => self::asStringOrNull($config['email_debug_from'] ?? null),
            // The default lives in Configuration too; repeated here because this method no
            // longer runs the tree that would apply it.
            'email_debug_title' => self::asStringOrNull($config['email_debug_title'] ?? 'An error occured'),
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
