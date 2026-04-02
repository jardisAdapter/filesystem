# Jardis Filesystem

![Build Status](https://github.com/jardisAdapter/filesystem/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm Shield](https://img.shields.io/badge/License-PolyForm%20Shield-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

Filesystem abstraction for local and S3-compatible cloud storage. Unified API for read, write, delete, list, stream, and visibility operations. No Flysystem dependency, no AWS SDK — only cURL and PHP builtins.

---

## Installation

```bash
composer require jardisadapter/filesystem
```

## Usage

```php
use JardisAdapter\Filesystem\FilesystemService;
use JardisAdapter\Filesystem\Config\LocalConfig;
use JardisAdapter\Filesystem\Config\S3Config;

$service = new FilesystemService();

// Local filesystem
$local = $service->create(new LocalConfig('/var/app/storage'));

$local->write('uploads/photo.jpg', $content);
$local->read('uploads/photo.jpg');
$local->exists('uploads/photo.jpg');
$local->delete('uploads/photo.jpg');

// S3-compatible storage
$s3 = $service->create(new S3Config(
    bucket: 'my-bucket',
    region: 'eu-central-1',
    key: 'AKIAEXAMPLE',
    secret: 'secret',
));

$s3->write('backups/dump.sql', $content);
$s3->copy('backups/dump.sql', 'backups/dump-copy.sql');
```

Multiple instances per project are supported — create as many as needed for different storage backends.

### Via Resource Chain (Jardis Foundation)

```php
$uploads = $this->getResource()->filesystem()->create(new LocalConfig('/storage/uploads'));
$backups = $this->getResource()->filesystem()->create(new S3Config(...));
```

---

## License

[PolyForm Shield License 1.0.0](LICENSE.md) — free for all use including commercial. Only restriction: don't build a competing framework.
