<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
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
| `Command\PurgeCommand` (`core:purge`) | `composer require symfony/console symfony/lock` — and `symfony/expression-language` only if a policy uses a `condition` |
| `Twig\NumberExtension`, `Twig\PdfAssetExtension` | `composer require twig/twig` (registered only when Twig is present) |
| `Form\Extension\NumberTypeGroupingExtension` | `composer require symfony/form` |
| `Controller\AbstractController` (et `addFormError()`) | `composer require symfony/framework-bundle symfony/form` |
| `Controller\BulkActionRunner` | `composer require symfony/security-csrf` (le service n'est enregistré que si le gestionnaire de jetons existe) |
| `Controller\BulkActionRunner` | `composer require symfony/security-csrf` (and the ORM) |

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

HTTP security headers
---------------------

Defence in depth against an XSS escalating into a take-over. **Off by default**: installing a
utility bundle must not change the responses of an application that did not ask — a lone
`X-Frame-Options: DENY` breaks any legitimate embedding.

```yaml
# config/packages/core.yaml
core:
    security_headers:
        enabled: true
        csp_enforce: false        # start here, always
```

That much already sends `X-Content-Type-Options: nosniff`,
`Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`, a closed
`Permissions-Policy` and a one-year `Strict-Transport-Security`, plus a
`Content-Security-Policy-Report-Only`.

**Only missing headers are filled.** A controller that set its own keeps it — a CMS preview
that needs `SAMEORIGIN` to survive its own iframe still works, without an exception list here.

Tune it per header, and drop one with `null`:

```yaml
core:
    security_headers:
        enabled: true
        headers:
            X-Frame-Options: 'SAMEORIGIN'
            Strict-Transport-Security: ~      # not sent at all
            X-Robots-Tag: 'noindex'           # extra headers are allowed too
        csp_policy: "default-src 'self'; connect-src 'self' https://mercure.example.com"
```

> ⚠️ **Two traps.** The default policy keeps `connect-src` closed to `'self'`, because a
> library cannot know which hosts your application talks to: an EventSource, an analytics
> endpoint or a CDN needs `csp_policy` widened, or it fails **silently in the browser**.
> And `csp_enforce: true` before reading the violation reports is how a working page stops
> loading its own assets — report-only first, always.

Captcha
-------

An arithmetic challenge for public forms — register, password reset — where bots submit
payloads just to make the application send mail.

```php
// rendering the form
return $this->render('security/register.html.twig', [
    'captchaQuestion' => $this->captcha->generate(),   // "3 + 5"
]);

// handling the submission
if (!$this->captcha->validate($request->request->getString('captcha'))) {
    // refuse, and call generate() again for the next attempt
}
```

```yaml
core:
    captcha:
        operations: ['+', '-', '*']            # default: ['+']
        session_key: '_math_captcha_answer'
```

`generate()` stores the expected answer in the session and returns the text to display.
`validate()` checks the submission and **consumes** the stored answer whatever the outcome, so
a right answer cannot be replayed and a wrong one forces a fresh question — **call
`generate()` again on every re-render**, or the next attempt validates against nothing.
Subtractions never ask for a negative answer, since only digits are accepted.

> ⚠️ Form-only by design. For a JSON client use reCAPTCHA or hCaptcha instead: a challenge
> whose answer lives in the caller's own session is worth little to an API consumer.

Retention
---------

Annotate an entity with `Attribute\Purgeable` and `core:purge` removes the rows whose
retention has expired. The attribute is repeatable, because one entity often needs two
delays:

```php
use Jul6Art\CoreBundle\Attribute\Purgeable;

#[Purgeable(field: 'createdAt', interval: '-3 months')]
#[Purgeable(field: 'deletedAt', interval: '-1 week', condition: 'entity.isDeleted()')]
class AuditLog { … }
```

```shell
bin/console core:purge --dry-run          # says what it would remove, removes nothing
bin/console core:purge --entity=AuditLog  # one entity only
bin/console core:purge
```

**Measure before you commit to an interval.** `--dry-run` reports the row count, and a
policy that looks reasonable can turn out to delete most of a table on its first run.

The command exists only when `symfony/console` and `symfony/lock` are both installed, and
`framework.lock` is configured — no lock means no command rather than an unguarded one, since
two concurrent purges would race on the same rows. A prevented concurrent run exits
`SUCCESS`: a scheduler should not page anyone for a guard working as intended.

**It writes no journal of its own.** One `Event\EntityPurgedEvent` is dispatched per removed
row, after the flush, carrying scalars only — by then the entity is detached. Subscribe to it
to record whatever your application needs:

```php
#[AsEventListener(event: EntityPurgedEvent::NAME)]
public function onEntityPurged(EntityPurgedEvent $event): void
{
    $this->auditLogger->log('entity.purged', $event->getOrganizationId(), null,
        $event->getEntityShortName(), $event->getEntityId());
}
```

```yaml
# config/packages/core.yaml
core:
    purge:
        batch_size: 100          # rows flushed at a time; lower it for heavy entities
        aliases: ['app:purge']   # keeps a legacy name alive so a deployed crontab survives
```

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

Controllers
-----------

`Controller\AbstractController` carries the redirect-and-flash vocabulary a controller repeats
on every write. Each helper flashes a message **already translated in its own domain**, so a
controller never names a domain and never injects the translator just to say "saved".

```php
final class UserController extends AbstractController
{
    public function edit(Request $request, User $user): Response
    {
        // …
        return $this->redirectBackWithSuccess($request, 'user.updated', 'user_index');
    }
}
```

Three redirects worth telling apart:

| Helper | Where it lands |
| --- | --- |
| `redirectWithSuccess()` / `redirectWithError()` | a named route, with a flash |
| `redirectBack()` / `redirectBackWithSuccess()` | the Referer **when it is same-origin**, otherwise a fallback route — for an entity reachable from several screens, it is the only thing that knows where the user came from. A Referer on another host is ignored rather than trusted: following it would be an open redirect. |
| `redirectAfterSave()` | the detail page, or an empty creation form when the request carries `_after_save=new` — the "save and create another" workflow. Asking for it without a creation route falls back rather than failing. |
| `redirectAfterDelete()` | always the index, **ignoring the Referer** on purpose: after a soft delete, the detail page it points at would 404. |

Plus `addSuccessFlash()` / `addErrorFlash()` / `addWarningFlash()`, and `addFormError()` — which
pre-translates, because `form_errors(form)` looks a key up in the `validators` domain and renders
it raw when it is missing.

```yaml
core:
    flash:
        default_domain: 'messages'
        domain_map:
            'organization.domain.': 'domain'    # longer prefix first
            'organization.': 'organization'
            'user.': 'user'
```

> ⚠️ **The map's order is significant.** The first matching prefix wins, so a longer prefix must
> be declared before the shorter one it starts with — otherwise `organization.domain.added` is
> translated in the `organization` domain and never reaches `domain`.

Configure nothing and you get plain Symfony behaviour: every flash in the default domain. That is
why an application can adopt this base class before deciding how to split its translations.

### When the domain belongs to the screen, not to the key

The map above assumes keys carry their domain as a prefix. Plenty of applications do the
opposite: one catalogue per screen, and short keys inside it — `edit.success` in `profile`,
`invalid_data` in `report`. No prefix map can express that, because the domain is not a function
of the key. Override `translationDomain()` instead, and map nothing:

```php
final class ProfileController extends AbstractController
{
    protected function translationDomain(): string
    {
        return 'profile';   // flashes, form errors and trans() alike
    }

    public function edit(Request $request): Response
    {
        // …
        return $this->redirectWithSuccess('profile_show', 'edit.success');   // → domain 'profile'
    }
}
```

A controller's own domain **wins over the map**, so a key that happens to look like a mapped
prefix still lands where the controller said.

`trans()` translates in that same domain without flashing anything — the message of a
`createNotFoundException()`, or a string interpolated into another message, lives in the same
catalogue, and it was usually the last reason to inject the translator:

```php
throw $this->createNotFoundException($this->trans('user.not_found'));
```

> ⚠️ **Both helpers degrade to the raw key** when `FlashTranslator` is absent from the container —
> which is what happens to a controller instantiated with `new` in a unit test. Assert on
> behaviour, not on the message, in those tests, or boot the container.

Bulk actions
------------

`Controller\BulkActionRunner` is the data-table bulk-action pattern in one place: validate the
CSRF token, parse `ids[]`, load every row in **one** query, check the voter row by row, and run a
business callable on each.

```php
#[Route('/bulk-publish', methods: ['POST'])]
#[IsGranted(PermissionCodes::CMS_PAGE_PUBLISH)]
public function bulkPublish(Request $request, BulkActionRunner $runner): Response
{
    $count = $runner->run($request, Page::class, PageVoter::PUBLISH, function (Page $page): void {
        if ($page->isPublished()) {
            throw new \DomainException('Already published.');   // this row only
        }

        $this->pageService->publish($page);
    });

    return $this->redirectWithSuccess('cms_page_index', 'cms.page.bulk_published', ['%count%' => $count]);
}
```

The return value is the number of rows the action actually ran on — what a flash should report.

**Throw `\DomainException` to refuse one row.** It is caught per row, so a rule refusing one
invoice does not cost the other twenty-four. Any other throwable rolls the **whole batch** back:
a database error signals corruption that must not leave a half-persisted state. The batch runs in
a single enveloping transaction rather than one per row.

`runOnSoftDeleted()` is the restore variant: it suspends the soft-delete filter for the fetch —
the rows would be invisible otherwise — and skips anything not actually deleted, so restoring an
already-active row is a silent no-op. It expects the filter to be named `soft_delete`; pass a
different name to the constructor if yours differs.

> ⚠️ Keeping `#[IsGranted]` on the route is **not** optional. The per-row voter check inside the
> runner is the second guard, not the first: without the attribute, an aggregate endpoint answers
> to anyone logged in.

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

Number formatting
-----------------

One service, so a figure looks the same in an HTML view, a PDF and a JSON payload — instead of
each template choosing its own `number_format()` arguments.

```twig
{{ invoice.total|format_number }}        {# 1 234,56 #}
{{ invoice.total|format_number(0) }}     {# 1 235 #}
{{ invoice.total|format_money('EUR') }}  {# 1 234,56 EUR #}
{{ line.vatRate|format_percent }}        {# 20 % #}
```

```php
public function __construct(private readonly NumberFormatter $formatter) {}
// …
$this->formatter->formatMoney($invoice->getTotal(), 'EUR');
```

```yaml
core:
    number_format:
        decimal_separator: ','
        thousands_separator: ~     # default: a non-breaking space
        decimals: 2
```

The defaults follow the French / Luxembourg convention. The thousands separator is a
**non-breaking space** on purpose: a regular one lets a PDF renderer wrap a number across two
lines. The percent sign is glued the same way.

Nothing to format returns an **empty string**, never a `0` or a dash — so the template decides:
`{{ value|format_number ?: '—' }}`.

> The filters are also registered as `fr_number`, `fr_money` and `fr_percent`. Those are
> historical names kept for existing templates; use the neutral ones in new code.

PDF assets
----------

`asset()` returns an HTTP URL relative to the current request. dompdf does not fetch remote
URLs in production and has no base to resolve a schemeless relative one — so the image
**silently never loads**. These two helpers are the way around it.

```twig
{# filesystem path, when dompdf may read the directory #}
<img src="{{ pdf_image_path(organization.logoPath) }}">

{# base64 data: URI, which no chroot or isRemoteEnabled setting can block #}
<img src="{{ pdf_image_data_uri(organization.logoPath) }}">
```

```yaml
core:
    pdf:
        public_dir: '%kernel.project_dir%/public'
```

Prefer the data URI for small images — logos, headers — at the cost of roughly a third more
HTML weight; prefer the path when a filesystem location is what is wanted. Both return `null`
on an empty input, so a template keeps its `{% if %}` unchanged.

> ⚠️ `pdf_image_data_uri()` refuses a file under 100 bytes. A truncated upload would otherwise
> produce a well-formed URI that dompdf renders as a **white square** — worse than no image,
> because nothing signals the failure.

Form bricks
-----------

**`Form\Transformer\StripWhitespaceTransformer`** reconciles an input mask with a fixed-length
column. A mask like `000 000 000 00000` (SIRET) posts the spaces it drew, and `Assert\Length`
then rejects the value for being too long:

```php
$builder->get('siret')->addModelTransformer(new StripWhitespaceTransformer(digitsOnly: true));
$builder->get('iban')->addModelTransformer(new StripWhitespaceTransformer());
```

`digitsOnly` drops everything that is not a digit; the default drops whitespace only, so an
IBAN keeps its letters. The displayed value is left untouched — the mask redraws itself on
connect. An emptied field reaches the entity as `null`, not `''`, so a nullable column does not
end up storing an empty string no `Assert\NotBlank` would catch.

**`Form\Extension\NumberTypeGroupingExtension`** turns on thousands grouping for every
`NumberType` at once, so a quantity renders as `1 234,56` rather than `1234.56`:

```yaml
core:
    form:
        number_grouping: true
```

> ⚠️ **Opt-in, and deliberately so**: it changes how every numeric field of the application
> looks, which is not a decision a bundle should make on installation. Submission stays
> backward compatible — `NumberToLocalizedStringTransformer` parses a grouped value as readily
> as an ungrouped one — and a single field can still opt out with `'grouping' => false`, for a
> numeric identifier that must not be grouped.

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

The Core Bundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [jul6art](https://devinthehood.com)
