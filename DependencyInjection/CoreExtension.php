<?php

namespace Jul6Art\CoreBundle\DependencyInjection;

use Exception;
use Monolog\Formatter\HtmlFormatter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class CoreExtension.
 */
class CoreExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');

        $this->addAnnotatedClassesToCompile([
            'Jul6Art\\CoreBundle\\',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $key => $parameter) {
            $container->setParameter(sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }

        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['MonologBundle'])) {
            if ('prod' === $container->getParameter('kernel.environment')) {
                $container->prependExtensionConfig('monolog', [
                    'handlers' => [
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
                            'type' => 'rotating_file',
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
                    ],
                ]);
            }

            if ($config['email_debug']) {
                $emailDebugFrom = $config['email_debug_from'];
                $emailDebugTitle = $config['email_debug_title'];
                $emailDebugTo = $config['email_debug_to'];

                if (null === $emailDebugFrom or !filter_var($emailDebugFrom, FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception(sprintf('Parameter "%s.%s" must be configured to activate email debug', $this->getAlias(), 'email_debug'));
                }

                if (null === $emailDebugTitle or !strlen($emailDebugTitle)) {
                    throw new \Exception(sprintf('Parameter "%s.%s" must be configured to activate email debug', $this->getAlias(), 'email_title'));
                }

                if (null === $emailDebugTo or !filter_var($emailDebugTo, FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception(sprintf('Parameter "%s.%s" must be configured to activate email debug', $this->getAlias(), 'email_to'));
                }

                if (isset($bundles['SwiftmailerBundle'])) {
                    $container->prependExtensionConfig('monolog', [
                        'handlers' => [
                            'swift' => [
                                'content_type' => 'text/html',
                                'formatter' => HtmlFormatter::class,
                                'from_email' => $emailDebugFrom,
                                'level' => 'critical',
                                'subject' => $emailDebugTitle,
                                'to_email' => $emailDebugTo,
                                'type' => 'swift_mailer',
                            ],
                            'deduplicated' => [
                                'handler' => 'swift',
                                'type' => 'deduplication',
                            ],
                        ],
                    ]);
                } else {
                    $container->prependExtensionConfig('monolog', [
                        'handlers' => [
                            'symfony_mailer' => [
                                'content_type' => 'text/html',
                                'formatter' => HtmlFormatter::class,
                                'from_email' => $emailDebugFrom,
                                'level' => 'critical',
                                'subject' => $emailDebugTitle,
                                'to_email' => $emailDebugTo,
                                'type' => 'symfony_mailer',
                            ],
                            'deduplicated' => [
                                'handler' => 'symfony_mailer',
                                'type' => 'deduplication',
                            ],
                        ],
                    ]);
                }
            }
        }
    }
}
