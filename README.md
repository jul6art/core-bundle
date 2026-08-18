<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood"></a>
</p>

<p align="center">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v2&color=orange" alt="Version">
</p>

jul6art/core-bundle
==============
Symfony core bundle
----------------------

Requirements
------------

* **php ^8.5**
* **symfony ^7.4 || ^8.0**

Installation
------------

```shell
composer require jul6art/core-bundle
```

Optional packages
-----------------

The bundle ships a few opt-in bricks whose dependencies are deliberately left out
of the runtime requirements. Install them yourself when you use the matching
feature:

| Feature | Package |
| --- | --- |
| `Service\Traits\FakerAwareTrait` (data fixtures) | `composer require --dev fakerphp/faker` |
| `core.email_debug` handler | `composer require symfony/monolog-bundle symfony/mailer` |

Start server
------------

```shell
cd my_symfony_application
symfony server:start
```

Configuration
-------------

Every option is optional. `email_debug` forwards critical logs by email through
Monolog and requires `symfony/monolog-bundle` plus `symfony/mailer`.

```yaml
# config/packages/core.yaml
core:
    email_debug: false
    email_debug_from: ~
    email_debug_title: 'An error occured'
    email_debug_to: ~
```

Each option is also exposed as a container parameter, prefixed with `core.`
(`core.email_debug`, `core.email_debug_from`, ...).

Quality assurance
-----------------

```shell
composer qa           # coding standards, Rector, static analysis and tests
composer test         # PHPUnit
composer phpstan      # PHPStan, level max
composer cs           # PHP-CS-Fixer, writes the fixes
composer rector       # Rector, writes the fixes
```

`cs-check` and `rector-check` are the read-only variants used by the CI.

License
-------

The Symfony Skeleton Generator is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [jul6art](https://devinthehood.com)
