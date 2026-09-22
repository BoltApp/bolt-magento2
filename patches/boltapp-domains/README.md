# bolt.com to boltapp.com patches

Bolt is moving the hosts this plugin talks to at runtime from `bolt.com` to
`boltapp.com`. **Version 3.0.0 and later already include this change** — if you can
upgrade, do that instead of patching:

```bash
composer require boltpay/bolt-magento2:3.0.0
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

These patches exist for stores that cannot take a full upgrade — typically because
they carry custom changes on top of a specific release. Each one applies to exactly
one version and changes nothing else.

## Pick the patch matching your installed version

Check which version you are on:

```bash
composer show boltpay/bolt-magento2 | head -3
```

Then take `bolt-magento2-<your-version>-boltapp-domains.patch` from this directory.
Patches are available for 2.13.0, 2.24.0, 2.24.1, 2.25.2, 2.26.1, 2.26.4, 2.27.1,
2.27.2, 2.27.3, 2.27.4, 2.27.5, 2.27.7 and 2.27.8.

A patch only applies to its own version. Applying the 2.27.8 patch to a 2.25.2
install will fail rather than half-apply.

## Applying it

Composer installs this plugin into `vendor/boltpay/bolt-magento2/`, and **anything
edited there is erased by the next `composer install` or `composer update`.** Pick
one of these instead.

### Option A — install a pre-patched branch (simplest)

Bolt publishes a branch per version with the change already applied:

```bash
composer require boltpay/bolt-magento2:dev-boltapp-domains/2.27.8
```

To freeze it so a later `composer update` cannot move it, pin the commit:

```bash
composer require boltpay/bolt-magento2:dev-boltapp-domains/2.27.8#<commit>
```

### Option B — composer-patches

Add [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) and
declare the patch under `extra.patches` in your root `composer.json`. It reapplies
on every install, so the change survives composer operations without a fork.

### Option C — apply it by hand

From the plugin root, on a checkout of the matching version:

```bash
git apply bolt-magento2-2.27.8-boltapp-domains.patch
# or, without git:
patch -p1 < bolt-magento2-2.27.8-boltapp-domains.patch
```

Only durable if the plugin lives in `app/code/Bolt/Boltpay/` rather than `vendor/`.

### After applying, either way

```bash
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

Magento caches compiled config, so skipping the flush leaves the old URLs in use.

## Checking it worked

```bash
grep -rnE '(api|connect|account|merchant|status)(-sandbox)?\.bolt\.com' \
  Helper/ etc/integration/ view/ --include='*.php' --include='*.xml' --include='*.phtml'
```

That should print nothing. In a browser, your storefront should load
`connect.boltapp.com/connect.js` with no CSP violation in the console, and a test
order should complete normally.

`etc/csp_whitelist.xml` and `Helper/SSOHelper.php` still mention `bolt.com` on
purpose — see below.

## What the patch changes

The Bolt hosts the plugin calls at runtime move to `boltapp.com`: `api`,
`api-sandbox`, `connect`, `connect-sandbox`, `account`, `account-sandbox`,
`merchant`, `merchant-sandbox` and `status`.

Two changes are **additive rather than replacements**, so your store keeps working
on both domains while the migration is in flight:

- `etc/csp_whitelist.xml` gains a `boltapp.com` entry beside each existing
  `bolt.com` one, so your Content Security Policy allows either origin.
- `Helper/SSOHelper.php` accepts `boltapp.com` token issuers alongside the
  `bolt.com` ones, so Bolt SSO keeps validating through the change.

`Helper/Config.php::validateCustomUrl` is widened to accept `boltapp.com`. If you
have set a custom Bolt URL in the admin, it would otherwise be rejected silently
and replaced with the default.

Unit tests are updated alongside the code, so applying a patch does not leave a
failing build.

### Deliberately unchanged

Copyright headers, the `integrations@bolt.com` contact address, `CHANGELOG.md`, and
the `docs.bolt.com` help links in the admin.

### Version-specific notes

- **2.13.0** predates `Helper/SSOHelper.php` and `etc/csp_whitelist.xml`, so that
  patch carries neither change. It also means the plugin ships no CSP policy at
  all — if your storefront enforces CSP, `connect.boltapp.com` has to be allowed
  wherever that policy is configured.
- **2.24.0 and 2.24.1** predate `etc/integration/config.xml`, so they carry no
  `status.bolt.com` change.
