<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Symfony\Symfony80\Rector\Class_\RemoveEraseCredentialsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/CoreBundle.php',
        __DIR__.'/DependencyInjection',
        __DIR__.'/Entity',
        __DIR__.'/EntityListener',
        __DIR__.'/Event',
        __DIR__.'/EventListener',
        __DIR__.'/Factory',
        __DIR__.'/Manager',
        __DIR__.'/Repository',
        __DIR__.'/Service',
        __DIR__.'/Tests',
    ])
    // No argument: the target PHP version is read from the "php" constraint in
    // composer.json, so the rule set follows the bundle instead of drifting.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withComposerBased(doctrine: true, symfony: true, phpunit: true)
    ->withSkip([
        // Pure helpers are deliberately static: it documents that they touch no state.
        LocallyCalledStaticMethodToNonStaticRector::class,
        // Doctrine entities keep their mapped properties out of the constructor, so
        // the test fixtures stay representative of real consumer code.
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__.'/Tests/Fixtures/Entity',
        ],
        // ⚠️ `eraseCredentials()` left `UserInterface` in Symfony 8, and Rector reasons on the version
        // installed locally: it removes the method from the test users. It is still declared by the
        // interface on 7.4, where the classes would turn abstract and fail to load — the `lowest deps`
        // job. A bundle promising `^7.4 || ^8.0` keeps both. Same skip as admin-bundle.
        RemoveEraseCredentialsRector::class,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
