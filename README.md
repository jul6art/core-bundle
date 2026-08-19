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
| `Security\Encryptor`, `Doctrine\Type\EncryptedStringType` | `ext-sodium` (bundled with PHP, but a distribution can omit it) |

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
    encryption_key: ~
```

The `email_debug*` options are also exposed as container parameters, prefixed with
`core.` (`core.email_debug`, `core.email_debug_from`, ...). **`encryption_key` is
deliberately not**, so the secret never ends up in the compiled container.

Data at rest
------------

Setting `core.encryption_key` to a base64-encoded 32-byte key registers
`Security\Encryptor` (libsodium XSalsa20-Poly1305 secretbox) and the listener that
feeds it to the `encrypted_string` DBAL type. Leave the key unset and nothing is
registered — an application that encrypts nothing carries no dead service.

```yaml
# config/packages/core.yaml
core:
    encryption_key: '%env(APP_ENCRYPTION_KEY)%'   # never commit the value

# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            encrypted_string: Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType
```

```php
#[ORM\Column(type: 'encrypted_string', nullable: true)]
private ?string $iban = null;
```

The ORM only ever sees the plaintext, so forms, validation and change tracking keep
working; the ciphertext exists only in the database. Each write uses a fresh nonce, so
the same plaintext never produces the same ciphertext twice, and decryption
authenticates the payload. Pass the key as an env var: it is read at runtime, not baked
into the container.

Soft delete
-----------

Three independent bricks, all opt-in from the application side:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            soft_delete:
                class: Jul6Art\CoreBundle\Doctrine\SoftDeleteFilter
                enabled: true
        dql:
            string_functions:
                JSON_TEXT: Jul6Art\CoreBundle\Doctrine\DQL\JsonTextFunction
```

- **`Doctrine\SoftDeleteFilter`** adds `AND <deletedAt column> IS NULL` to every query on
  an entity declaring a `deletedAt` field, and leaves the others alone. The column name
  comes from the mapping, so both naming strategies work.
- **`Service\CascadeSoftDeleteHelper`** (registered automatically when DoctrineBundle is
  enabled) carries the DQL UPDATE patterns for propagating a soft delete to children:
  `cascadeSoftDelete()`, `nullifyForeignKey()`, `cascadeRestore()`, plus
  `bulkMarkDeletedColumn()` / `bulkRestoreDeletedColumn()` to free UNIQUE columns by
  appending `Util\Strings::DELETED_SUFFIX`.
- **`Doctrine\DQL\JsonTextFunction`** exposes `JSON_TEXT(field)`, casting a JSON column
  to text so a portable `LIKE` can search it (`field::text` on PostgreSQL,
  `CAST(field AS CHAR)` elsewhere).

Voters
------

`Security\Voter\AbstractVoter` reduces a voter to its business rules. A concrete voter
states three things — the attributes it carries, the subject types it applies to, how it
decides — and the base class handles the rest: Symfony's two caching hooks, the
anonymous-visitor guard, and the role lookup.

```php
use Jul6Art\CoreBundle\Security\Voter\AbstractVoter;
use Symfony\Component\Security\Core\User\UserInterface;

final class GalleryVoter extends AbstractVoter
{
    public const string VIEW = 'GALLERY_VIEW';
    public const string EDIT = 'GALLERY_EDIT';

    protected function attributes(): array
    {
        return [self::VIEW, self::EDIT];
    }

    protected function subjects(): array
    {
        return [Gallery::class];
    }

    protected function decide(string $attribute, mixed $subject, UserInterface $user): bool
    {
        if (!$subject instanceof Gallery) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $subject->isPublished() || $this->owns($subject, $user),
            self::EDIT => $this->owns($subject, $user) || $this->hasRole('ROLE_ADMIN'),
            default => false,
        };
    }
}
```

What the base class gives you:

| Member | Role |
|---|---|
| `attributes()` | *abstract* — the attributes carried, listed explicitly. Feeds `supportsAttribute()`, which Symfony **caches**: the voter is never called again for an attribute it does not carry. |
| `subjects()` | *abstract* — the subject types. Feeds `supportsType()`, **cached** on the type name. Return `[]` when the decision rests on no entity (a dashboard, a global listing). |
| `decide()` | *abstract* — the rules, with a guaranteed non-anonymous `$user`. |
| `supportsSubject()` | Instance-level counterpart of `supportsType()`; override it when the rule is finer than a type. |
| `hasRole()` | Role of the signed-in account, **inheritance included**. |
| `setSecurity()` | `#[Required]` setter, so a concrete voter keeps its constructor for its own dependencies. |

> `hasRole()` goes through `Security::isGranted()` on purpose. `$token->getRoleNames()`
> returns only the roles actually **stored**, so an account holding `ROLE_ADMIN` and granted
> `ROLE_EDITOR` through `role_hierarchy` fails a raw check and passes this one. If you are
> replacing hand-written role checks, expect verdicts to change wherever a role was
> inherited rather than stored.

A missing subject is accepted (`supportsType('null')` is `true`), because an attribute that
carries no entity — `CREATE`, `LIST` — is a first-class case. Guard the type inside
`decide()` when an attribute does need its entity, as the example above does.

Utilities
---------

- **`Util\Strings`** — UTF-8-safe `upper()` / `lower()` normalisation for entity setters,
  plus `lowerEmail()` / `lowerHost()` which lowercase everything *except* a trailing
  `_DELETED_<timestamp>` soft-delete marker.
- **`Event\PersistenceAbortedException`** — thrown by `EntityListener\AbstractEntityListener`
  when a subscriber aborts a `BEFORE_*` event, so the refused write never reaches the
  database.

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
