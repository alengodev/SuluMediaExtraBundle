# SuluMediaExtraBundle

[![CI](https://github.com/alengodev/SuluMediaExtraBundle/actions/workflows/ci.yaml/badge.svg)](https://github.com/alengodev/SuluMediaExtraBundle/actions/workflows/ci.yaml)

Media utilities for [Sulu CMS](https://sulu.io/) 3.x — `@2x` crop synchronization, image format generation, and media rename command.

## Features

- **`@2x` crop sync** — automatically mirrors crop settings from `800x@2x` to `800x` on every crop save
- **Image format generation** — generates `image-formats.xml` entries from a simple YAML list (including `@2x` variants)
- **Format options duplicate** — backfills existing `@2x` crop entries to their base format keys
- **Media rename** — renames a media file in storage and database with dry-run support

## Requirements

- PHP 8.2+
- Sulu CMS ~3.0 (tested against 3.0 and 3.1)
- Symfony 6.4 LTS, 7.x, or 8.x

> **Compatibility:** Verified against Sulu 3.1 and Symfony 8.1. The `~3.0` Sulu
> constraint already covers 3.1, so it is pulled automatically once a stable
> 3.1 is released. See [Testing against dev versions](#testing-against-dev-versions)
> to try the current `3.1.x-dev` branch today.

## Installation

```bash
composer require alengo/sulu-media-extra-bundle
```

Register the bundle in `config/bundles.php`:

```php
Alengo\SuluMediaExtraBundle\AlengoMediaExtraBundle::class => ['all' => true],
```

No further configuration required — all services are auto-registered.

## Testing against dev versions

To try the bundle against the current `sulu/sulu:3.1.x-dev` branch and Symfony 8.1
**without** adding dev stability to the bundle's own `composer.json`, resolve it in a
throwaway project that references your local working copy via a path repository:

```bash
# from a scratch directory (not the bundle repo)
composer init --no-interaction --name=local/dev-test
composer config minimum-stability dev
composer config prefer-stable true
composer config repositories.bundle path /path/to/alengoMediaExtraBundle

composer require \
  "alengo/sulu-media-extra-bundle:*@dev" \
  "sulu/sulu:3.1.x-dev" \
  "symfony/console:~8.1.0" \
  "symfony/yaml:~8.1.0"
```

This installs the dev dependencies and symlinks the bundle, so you can verify
resolution and that all referenced Sulu/Symfony classes autoload against the dev
stack. The bundle's published constraints stay clean.

> Only `3.1.x-dev` exists today — there is no stable Sulu 3.1 release yet, so a real
> project still installs the latest stable 3.0.x unless it opts into dev stability.

## Development & tests

```bash
composer install
composer test        # or: vendor/bin/phpunit
```

The suite contains a service-wiring smoke test (boots the bundle in a real
Symfony kernel and asserts every service compiles) and a functional test of the
image-format generation. CI runs the suite on PHP 8.2–8.4 across `--prefer-lowest`
(Sulu 3.0 / Symfony 6.4 LTS), highest stable, and a `sulu/sulu:3.1.x-dev` lane.

## @2x Crop Sync

`MediaCropModifiedSubscriber` listens to `MediaCropModifiedEvent`. Whenever a crop is saved for a format key containing `@2x` (e.g. `800x@2x`), the same crop coordinates are automatically written to the base key (`800x`).

This ensures that `@1x` and `@2x` formats always use identical crop regions without manual duplication.

## Commands

### `alengo:media:image-formats:generate`

Generates `image-formats.xml` entries from a YAML source file. Each format produces two XML entries — the base format (marked `internal="true"`) and the `@2x` variant.

```bash
bin/adminconsole alengo:media:image-formats:generate
bin/adminconsole alengo:media:image-formats:generate --source=config/app/image-formats.yaml --target=config/image-formats.xml
```

**Source YAML format:**

```yaml
# config/app/image-formats.yaml
image_formats:
    - 400x300
    - 800x
    - x600
```

**Generated XML output** (appended to existing `image-formats.xml`):

```xml
<format key="400x300" internal="true">
    <scale x="400" y="300"/>
</format>
<format key="400x300@2x">
    <scale x="800" y="600"/>
</format>
```

| Option | Default | Description |
|---|---|---|
| `--source` / `-s` | `config/app/image-formats.yaml` | Source YAML file (relative to project root) |
| `--target` / `-t` | `config/image-formats.xml` | Target XML file (relative to project root) |

---

### `alengo:media:image-format-options:duplicate`

Backfills existing `@2x` crop entries (`me_format_options`) to their base format keys. Useful after adding the bundle to a project that already has saved crops.

```bash
bin/adminconsole alengo:media:image-format-options:duplicate
bin/adminconsole alengo:media:image-format-options:duplicate --source=config/app/image-formats.yaml
```

| Option | Default | Description |
|---|---|---|
| `--source` / `-s` | `config/app/image-formats.yaml` | YAML file listing the format keys to process |

---

### `alengo:media:rename`

Renames a media file — updates the display name in the database, moves the file in storage, and increments the subVersion to invalidate format caches.

```bash
bin/adminconsole alengo:media:rename <mediaId> <newName>
bin/adminconsole alengo:media:rename 42 "new-filename" --locale=de --dry-run
```

| Argument / Option | Description |
|---|---|
| `mediaId` | ID of the media to rename |
| `newName` | New base filename (extension is preserved from original) |
| `--locale` / `-l` | Locale for filename normalization (default: `en`) |
| `--dry-run` | Preview changes without writing anything |
