# Contributing to `jul6art/core-bundle`

`jul6art/core-bundle` is the foundation the rest of this ecosystem is built on:
`AbstractRepository`, `AbstractManager`, `IdTrait`, `FactoryInterface`, the event and entity
listener base classes, the `*AwareTrait` setters, soft delete, field encryption, DQL
functions, formatting helpers and a performance profiler. Every other bundle depends on it,
which is why its test suite is the largest here.

That origin sets the tone for contributions: this bundle carries what proved necessary in a
real application, and it stops where the application's own business begins. A pull request
that adds a case nobody has hit yet is a harder sell than one that fixes a case you did hit
— say which one yours is.

Read [README.md](../README.md) first; it documents the configuration, the
contracts and the traps already paid for. This file covers the workflow.

## Before you open anything

* **Bug** → open an [issue](https://github.com/jul6art/core-bundle/issues/new/choose)
  with the bundle version, the Symfony version, and the shortest failing test
  you can write. A failing test is worth more than a description, and it is what
  a fix will be built on.
* **New feature, new configuration key, new contract method** → open an issue
  first. This bundle is a dependency of applications in production, so its
  public surface is a promise; adding to it is cheaper to discuss before the
  code than after.
* **Security problem** → do not open an issue. Follow [SECURITY.md](SECURITY.md).

## Setting up

```bash
git clone https://github.com/jul6art/core-bundle.git
cd core-bundle
composer install
```

You need PHP **^8.5** and Composer 2. There is nothing to boot: the bundle is exercised
through its test kernel, which the suite builds for you.

## The quality gate

```bash
composer qa
```

That is the whole contract, and it runs the four checks in the order the CI runs them:

| Step | What it is | Fix it with |
| --- | --- | --- |
| `cs-check` | php-cs-fixer, `--dry-run --diff` | `composer cs` |
| `rector-check` | Rector, `--dry-run` | `composer rector` |
| `phpstan` | PHPStan at **`level: max`** | by hand — a baseline entry is a last resort, not a shortcut |
| `test` | PHPUnit (50 test classes today) | by hand |

`composer qa` green is the minimum for a pull request, not the goal. The suite is configured
to fail on deprecations, notices, warnings and risky tests, so a test that passes while
emitting a deprecation is a failing test here.

## What the CI checks that your machine does not

[`.github/workflows/ci.yml`](workflows/ci.yml) runs three jobs, and two of them
catch what a local run cannot:

* **The dependency matrix** — the test suite runs on both `highest` **and**
  `lowest` dependencies. The `lowest` set is not a formality: across this
  ecosystem it has caught a typed parameter incompatible with an older
  `psr/log`, and vendor deprecations three separate times, each one green
  locally. If you widen a constraint in `composer.json`, that job is the one
  that says whether you may.
* **`composer validate --strict`** — a malformed or inconsistent
  `composer.json` fails the build.
* `SYMFONY_REQUIRE=7.4.*` pins the whole `symfony/*` set to one minor, so the
  matrix stays honest instead of letting Composer mix components from several
  branches.

Supported at the moment: **PHP ^8.5**, **Symfony ^7.4 || ^8.0**.

## Semantic versioning is a promise here

`jul6art/core-bundle` is installed by other projects through Composer, so the version number
is part of the interface:

* **patch** — a fix that changes no signature and no configuration key;
* **minor** — something added that existing code keeps working without;
* **major** — anything a dependent application must change code for: a removed
  or renamed configuration key, a new method on a contract interface, a
  narrowed parameter type, a changed default.

Adding a method to an interface the application implements is a **breaking change**, even
though PHP will not tell you so until someone upgrades. Say so in the pull request when
yours does.

## House rules

1. **The bundle carries what every application would otherwise rewrite; anything specific to
   one domain stays in the application.** That line is where most review comments land. When
   in doubt, say in the pull request why the code cannot live in the project that needs it.

2. **Anything added here lands in every application and every other bundle.** That is the
   whole argument for a high bar: a helper used twice belongs in the project that uses it,
   and only a third caller makes it foundational.

3. **Encryption uses the configured key and never invents its own scheme.** No home-made
   cipher, no reused initialisation vector, no key read from anywhere but the configuration.
   A change to the encryption service ships tests for the round trip and for what happens
   when the key is wrong or missing.

4. **Soft delete means invisible by default.** Every read path added here excludes
   soft-deleted rows unless the caller explicitly asks for them, and the interaction between
   soft delete and `UNIQUE` columns stays as documented in the README — that one cost an
   afternoon already.

5. **Serialization groups do not belong in a trait.** The README says why; a trait that
   carries them decides, for every consumer, what leaves the application.

6. **The actor is recorded, not guessed** — including for impersonation and for contexts
   with no authenticated user, such as a console command or a message handler.

7. **The performance profiler stays a development tool.** Nothing it collects may be
   reachable or enabled in production by default.

8. **The shipped front-end assets stay build-free for the bundle itself.** There is no
   `package.json` here and there should not be one: the assets are consumed by the
   application's build, and a bundle that needs npm to be installed is a bundle nobody
   installs.

9. **`level: max`, and a baseline entry is a last resort.** A `mixed` reaching a Doctrine
   expression is exactly the class of bug this setting was turned up to catch; silencing it
   moves the cost to whoever upgrades.

10. **A new runtime dependency is a discussion, not a commit.** Everything in `require` is
    imposed on every application installing this bundle. Prefer `suggest` plus a graceful
    degradation, which is how the optional integrations here already work.

11. **Nothing sensitive reaches a log or an exception message** — no password, no token, no
    key material, no personal data. An exception message is read by whoever can see a stack
    trace.

## Tests

Tests live in `Tests/`, mirroring the source tree. `Tests/Fixtures/TestKernel.php` boots a
real container rather than a mock, and `Tests/Functional/` uses it — so a service that no
longer compiles fails the suite instead of failing an application on install. When you add a
configuration key, wire it in that kernel too.

A bug fix comes with the test that fails without it. That is not a formality: it is how a
fix survives the next refactoring.

## Pull requests

* One subject per pull request.
* Fill in the [template](pull_request_template.md), including the `composer qa`
  result — a pull request that does not say whether the gate is green cannot be
  reviewed.
* Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/):
  `fix: …`, `feat: …`, `docs: …`, `chore: …`, and `feat!:` / `fix!:` for a
  breaking change. This repository's history is written in French; French and
  English are both fine, and the code, comments and documentation stay in
  English.
* Update the README in the same pull request when you add or change a
  configuration key, a contract or a public service — the README is the
  reference for all three.
* Rebase on `master` rather than merging it back in.

## Code of conduct

Participation is covered by our [Code of Conduct](CODE_OF_CONDUCT.md).

## License

Contributions are accepted under the [MIT license](../LICENSE) that covers this
repository.
