# Jardis Filesystem

![Build Status](https://github.com/jardisAdapter/filesystem/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm Shield](https://img.shields.io/badge/License-PolyForm%20Shield-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Zero Dependencies](https://img.shields.io/badge/Dependencies-Zero-brightgreen.svg)](composer.json)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

**File operations without the framework.** A lean filesystem abstraction for local and S3-compatible storage — designed for DDD applications that store uploads, manage assets, or sync backups. No Flysystem, no AWS SDK, no dependency bloat. Just cURL and PHP builtins.

---

## Why This Filesystem?

- **Two classes are enough** — `FilesystemService` + a config object, nothing else
- **Multiple instances** — local for uploads, S3 for backups, both in the same project
- **Atomic handler pipeline** — each operation is its own invokable, orchestrated by closures
- **Stream support** — read and write large files without memory overhead
- **S3 without the SDK** — AWS Signature v4 via cURL, works with MinIO, DigitalOcean Spaces, etc.
- **Security hardened** — path traversal protection, symlink containment, XXE prevention, secret masking
- **79% test coverage** — integration tests against real MinIO, not mocks

---

## Installation

```bash
composer require jardisadapter/filesystem
```

---

## Quick Start

### Local Filesystem

```php
use JardisAdapter\Filesystem\FilesystemService;

$service = new FilesystemService();
$fs = $service->local('/var/app/storage');

$fs->write('uploads/photo.jpg', $imageData);
$content = $fs->read('uploads/photo.jpg');
```

### S3-Compatible Storage

```php
$fs = $service->s3(
    bucket: 'my-bucket',
    region: 'eu-central-1',
    key: 'AKIAEXAMPLE',
    secret: 'wJalrXUtnFEMI/K7MDENG...',
);

$fs->write('backups/dump.sql', $sqlDump);
```

### Multiple Backends

```php
$uploads = $service->local('/storage/uploads');
$backups = $service->s3('company-backups', 'eu-central-1', $env('AWS_KEY'), $env('AWS_SECRET'));

// Upload lokal speichern
$uploads->write('invoice-2026.pdf', $pdf);

// Backup auf S3 sichern
$backups->write('daily/invoice-2026.pdf', $pdf);
```

### Advanced Configuration

For custom permissions, symlink settings, or other advanced options — use `create()` with a config object:

```php
use JardisAdapter\Filesystem\Config\LocalConfig;

$fs = $service->create(new LocalConfig(
    root: '/storage/uploads',
    filePermissions: 0600,
    dirPermissions: 0700,
    followSymlinks: false,
));
```

---

## File Operations

```php
$fs->write('file.txt', 'content');
$fs->read('file.txt');                  // string
$fs->exists('file.txt');                // bool
$fs->delete('file.txt');
$fs->copy('source.txt', 'target.txt');
$fs->move('old.txt', 'new.txt');
$fs->size('file.txt');                  // int (bytes)
$fs->lastModified('file.txt');          // int (unix timestamp)
$fs->mimeType('file.txt');              // string
```

---

## Stream Support

For large files — no memory overhead:

```php
// Write from stream
$stream = fopen('/tmp/video.mp4', 'rb');
$fs->writeStream('videos/intro.mp4', $stream);
fclose($stream);

// Read as stream
$stream = $fs->readStream('videos/intro.mp4');
while (!feof($stream)) {
    $chunk = fread($stream, 8192);
    // process chunk...
}
fclose($stream);
```

---

## Directory Operations

```php
$fs->createDirectory('uploads/2026');
$fs->deleteDirectory('uploads/2025');    // recursive

foreach ($fs->listContents('uploads', recursive: true) as $item) {
    echo $item->path();           // 'uploads/photo.jpg'
    echo $item->size();           // 1048576
    echo $item->lastModified();   // 1711929600
    echo $item->isFile();         // true
    echo $item->isDirectory();    // false
}
```

---

## Visibility

Control file permissions (local: Unix chmod, S3: ACL):

```php
$fs->setVisibility('public/logo.png', 'public');
$fs->setVisibility('private/secret.pdf', 'private');

$fs->getVisibility('public/logo.png');   // 'public'
```

---

## Configuration

### LocalConfig

```php
new LocalConfig(
    root: '/var/app/storage',       // required — validated via realpath()
    filePermissions: 0644,          // new files (default: 0644)
    dirPermissions: 0755,           // new directories (default: 0755)
    followSymlinks: true,           // follow symlinks (default: true)
    publicFilePerms: 0644,          // visibility 'public' files
    privateFilePerms: 0600,         // visibility 'private' files
    publicDirPerms: 0755,           // visibility 'public' directories
    privateDirPerms: 0700,          // visibility 'private' directories
)
```

### S3Config

```php
new S3Config(
    bucket: 'my-bucket',                        // required
    region: 'eu-central-1',                      // required
    key: 'AKIAEXAMPLE',                          // required
    secret: 'wJalrXUtnFEMI...',                  // required, masked in debug output
    endpoint: 'https://s3.amazonaws.com',        // default: AWS (use custom for MinIO etc.)
    prefix: 'uploads/',                          // path prefix in bucket (default: '')
)
```

The secret is protected with `#[\SensitiveParameter]` and masked in `var_dump()` / debug output.

---

## Error Handling

All exceptions implement `FilesystemExceptionInterface` — catch one, catch all:

| Exception | When |
|-----------|------|
| `FileNotFoundException` | File or directory does not exist |
| `UnableToReadException` | Read failure (permissions, I/O, S3 auth) |
| `UnableToWriteException` | Write failure (permissions, disk full, S3) |
| `UnableToDeleteException` | Delete failure |
| `FilesystemException` | Base — path traversal, null byte, invalid config |

```php
use JardisAdapter\Filesystem\Exception\FileNotFoundException;
use JardisSupport\Contract\Filesystem\FilesystemExceptionInterface;

try {
    $content = $fs->read('missing.txt');
} catch (FileNotFoundException $e) {
    // file does not exist
} catch (FilesystemExceptionInterface $e) {
    // any other filesystem error
}
```

---

## Architecture

The user only sees `FilesystemService` + config objects. Internally, the orchestrator builds a pipeline of atomic invokable handlers — one `__invoke` per operation:

```
FilesystemService (implements FilesystemServiceInterface)
  ├── local(root): FilesystemInterface
  ├── s3(bucket, region, key, secret): FilesystemInterface
  └── create(LocalConfig|S3Config): FilesystemInterface   ← advanced

Filesystem (Orchestrator)
  │
  │  PathNormalizer — traversal + null byte protection
  │
  ├── Local:
  │   LocalFullPath (symlink containment via realpath)
  │   + 16 atomic handlers: LocalRead, LocalWrite, LocalExists, ...
  │
  └── S3:
      S3Signer (AWS Signature v4)
      S3Request (shared cURL helper)
      + 16 atomic handlers: S3Read, S3Write, S3Exists, ...
```

Each handler is an **invokable object** (`__invoke`) — independently testable, replaceable, composable. The orchestrator extracts closures via `->__invoke(...)` and stores only the closures. No handler object survives as a property.

---

## Security

- **Path traversal** — `..` segments and null bytes rejected before any I/O
- **Symlink containment** — `realpath()` check ensures resolved paths stay inside root
- **Root validation** — `LocalConfig` resolves root via `realpath()` at construction time
- **XXE prevention** — `LIBXML_NONET` on all XML parsing (S3 responses)
- **Secret masking** — `S3Config::$secret` uses `#[\SensitiveParameter]` + `__debugInfo()`
- **Bucket wipe guard** — `deleteDirectory('')` with empty prefix is rejected

---

## Contracts

The package implements interfaces from `jardissupport/contract`:

| Interface | Purpose |
|-----------|---------|
| `FilesystemServiceInterface` | Factory: `local()`, `s3()` |
| `FilesystemInterface` | Full API (extends Reader + Writer) |
| `FilesystemReaderInterface` | Read-only subset — inject this for read-only contexts |
| `FilesystemWriterInterface` | Write-only subset |

```php
// Inject read-only access
public function __construct(
    private readonly FilesystemReaderInterface $storage,
) {}
```

---

## Jardis Foundation Integration

In a Jardis DDD project, the filesystem is available via the resource chain:

```php
$uploads = $this->getResource()->filesystem()->local('/storage/uploads');

$backups = $this->getResource()->filesystem()->s3(
    bucket: $env('FS_BACKUPS_BUCKET'),
    region: $env('FS_BACKUPS_REGION'),
    key: $env('FS_BACKUPS_KEY'),
    secret: $env('FS_BACKUPS_SECRET'),
);
```

No singleton, no handler — the developer decides how many filesystem instances exist and how they are configured. The resource chain returns `FilesystemServiceInterface`.

---

## Development

```bash
cp .env.example .env    # One-time setup
make install             # Install dependencies
make start               # Start MinIO (S3 integration tests)
make phpunit             # Run tests (118 tests, starts MinIO automatically)
make phpstan             # Static analysis (Level 8)
make phpcs               # Coding standards (PSR-12)
```

---

## License

[PolyForm Shield License 1.0.0](LICENSE.md) — free for all use including commercial. Only restriction: don't build a competing framework.
