# jardisadapter/filesystem

Unified filesystem abstraction for local and S3-compatible backends (AWS, MinIO, DO Spaces). Orchestrator-with-Closures pattern, hardened against path traversal and symlink escape.

## Usage Essentials

- **Entry point via `FilesystemService`:** `$service->local($root)`, `$service->s3($bucket, $region, $key, $secret, endpoint?, prefix?)`. For power users: `$service->create(new LocalConfig(...) | new S3Config(...))` — only on the concrete service, not on the interface.
- **No singleton.** Multiple `Filesystem` instances per project are the norm (e.g. uploads local, backups on S3).
- **Contracts split into Reader/Writer** (`FilesystemReaderInterface` + `FilesystemWriterInterface`). For read-only consumers inject only the reader.
- **Visibility (`public`/`private`) not in the Contract** — only on the concrete `Filesystem` object. Reflection/feature check before calling if necessary.
- **Security is built in and cannot be bypassed:** Path traversal + null bytes rejected, symlink escape via `realpath()` containment, bucket-wipe guard on empty S3 prefix, `LIBXML_NONET` against XXE. `S3Config::$secret` is masked via `#[\SensitiveParameter]` + `__debugInfo()`.
- **Exception hierarchy:** Catch `FileNotFoundException`/`FileExistsException`/`UnableTo*Exception` specifically; base is `FilesystemExceptionInterface` from `jardissupport/contract`.

## Full Reference

https://docs.jardis.io/en/adapter/filesystem
