# Security Policy

## Supported versions

`jul6art/core-bundle` is installed by other applications through Composer, so a fix here
reaches them the moment they update. Only the current major line gets one.

| Version | Supported |
| --- | --- |
| `2.x` | ✅ |
| `1.x` | ❌ |
| any older tag or fork | ❌ |

Support means security fixes on the latest release of that line — upgrade to it before
reporting, in case the problem is already gone.

## What is in scope

Every other bundle and every generated application depends on this one, so a defect here
multiplies. What matters most:

* **The encryption service** — a weak or hardcoded key path, a reused initialisation vector,
  a mode without integrity, a decryption failure that silently returns plaintext or an empty
  value instead of refusing.
* **Soft delete failing to hide** — a repository or DQL path returning deleted rows to a
  caller that did not ask, or a restore that resurrects a row past a uniqueness check.
* **Injection through a shared helper** — an `AbstractRepository` method, a DQL function or
  a formatting utility that interpolates a caller-supplied field name or value instead of
  binding it.
* **Over-exposure through serialization** — a trait or base class putting a field into a
  group, so an application leaks it without ever having said so.
* **The performance profiler or the debug mail transport reachable in production**, or
  collecting request data it then exposes.
* **A wrongly attributed actor** on a created-by / updated-by column, which every audit
  downstream then trusts.

Out of scope: vulnerabilities in Symfony, Doctrine, API Platform or any other third-party
package — report those to the project that owns the code, and they will reach you through
your own `composer update`. Also out of scope: an application that misconfigures this bundle
in a way the README warns against, though a warning that turns out to be easy to miss is
worth an issue of its own.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Use [GitHub's private vulnerability reporting](https://github.com/jul6art/core-bundle/security/advisories/new)
(the **Security** tab → *Report a vulnerability*). It opens a draft advisory only
you and the maintainers can read, and it is the channel this project prefers —
no email address needs to be published for it to work.

Please include:

* the version of `jul6art/core-bundle` and of Symfony you are running,
* the relevant part of your bundle configuration,
* the shortest reproduction you have — ideally a failing test against this
  repository, since that is what a fix will be built on,
* what an attacker gains: which check is bypassed, which data is read or
  written, and whether authentication is required.

## What to expect

* An acknowledgement within **7 days**.
* An assessment — accepted, out of scope, or needing more detail — within
  **14 days**.
* For an accepted report: a fix released on the supported line, a
  [security advisory](https://github.com/jul6art/core-bundle/security/advisories)
  describing the impact and the version to upgrade to, and credit in it unless
  you ask otherwise.

Please give the maintainers a reasonable window to ship a release before disclosing
publicly. This project runs no bug-bounty programme and offers no payment.