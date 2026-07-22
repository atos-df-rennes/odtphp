# Copilot Instructions for OdtPHP

## Build, Test, and Lint Commands

### Setup
```bash
composer install
```

### Testing
- **Run all tests**: `composer test`
- **Run a single test file**: `vendor/bin/phpunit tests/GoldenMaster/OdfGoldenMasterTest.php`
- **Run a specific test method**: `vendor/bin/phpunit tests/GoldenMaster/OdfGoldenMasterTest.php --filter testTutorial1`
- **With coverage**: `composer test-coverage` (generates HTML report in `tests/coverage/`)

### Code Quality
- **Static Analysis**: `composer analyse` (runs PHPStan with memory limit for large codebases)
- **Generate baseline**: `composer generate-baseline` (updates `phpstan-baseline.neon` with known issues)
- **Code formatting**: `composer cs-fix` (PHP CS Fixer with `@auto` rules, excludes `lib/`)
- **Refactoring**: `composer refactor` (Rector for automated modernization)

## Project Architecture

### Core Purpose
OdtPHP is a PHP library for generating OpenDocument Text (.odt) files from code. It uses a templating mechanism to populate ODT document templates with dynamic content.

### Main Components

**`src/Odf.php`** — Main templating class
- Handles ODT file loading, manipulation, and generation
- Manages configuration (ZIP proxy, delimiters, temp paths)
- Stores document content (content.xml, manifest.xml, styles.xml)
- Supports variable substitution, image insertion, and segment management
- Entry point for most library usage

**`src/Segment.php`** — ODT segment/section handling
- Represents repeatable sections within ODT templates (e.g., table rows, list items)
- Implements `IteratorAggregate` and `Countable` for iteration
- Stores child segments and variables within a section
- Handles XML parsing and cloning of segment templates

**`src/SegmentIterator.php`** — Iterator for segment traversal
- Provides iteration over `Segment` children
- Works with `Odf::$segments` collection

**`src/Zip/`** — ZIP handling abstraction
- `ZipInterface.php` — Defines contract for ZIP operations
- `PhpZipProxy.php` — Uses native PHP `ZipArchive` extension
- `PclZipProxy.php` — Falls back to PclZip library (in `lib/pclzip.lib.php`)
- Configuration in `Odf` allows runtime selection via `ZIP_PROXY`

**`src/Exceptions/`** — Exception types
- `OdfException` — General library errors
- `SegmentException` — Segment-specific issues
- `PhpZipProxyException` — ZIP extension errors
- `PclZipProxyException` — PclZip library errors

### Test Organization

**`tests/GoldenMaster/OdfGoldenMasterTest.php`** — Snapshot/characterization tests
- Preserves current behavior during modernization phases
- Each test reproduces one historical `tutorielN.php` example
- Snapshots are stored in `tests/GoldenMaster/__snapshots__/`
- Uses `OdtSnapshotTestCase` helper for snapshot management

**`tests/Unit/`** — Unit tests for specific components

**`tests/Fixtures/`** — Test data
- `odt/` — Sample ODT template files
- `images/` — Image files used in tests

**`tests/tutorielN.php`** — Historical examples and manual test cases

### Modernization Phases
The codebase is undergoing a structured modernization (see `rector.php`):
- **Phase 0**: Golden master tests (protect current behavior)
- **Phase 4**: Decision on PclZip vs native ZIP extension (affects `lib/` exclusion)
- Higher phases will update PHP version requirements and modernize patterns

## Key Conventions

### Coding Style
- **PSR-12 compliant** via PHP CS Fixer with `@auto` rules
- **Strict typing**: `declare(strict_types=1)` at file headers
- **Namespacing**: All source code under `Odtphp\` namespace
- **Autoloading**: PSR-4 `Odtphp\` → `src/` directory

### Type Hints
- Modern code uses full type hints (parameter and return types)
- Rector targets progressive type coverage
- Legacy code may have older documentation style (`@param`, `@return` tags)

### Error Handling
- Throw domain-specific exceptions from `src/Exceptions/` (not generic `Exception`)
- Use `OdfException` for general errors; use specific exception types for detailed categories

### Testing Patterns
- Use `OdtSnapshotTestCase` for behavior preservation tests
- New tests should use `phpunit/phpunit ^9.6` syntax
- Golden master tests use `@covers` annotations to track coverage
- Fixtures are in `tests/Fixtures/` directory tree

### Exclusions
- `lib/` excluded from PHP CS Fixer (external PclZip library, maintained separately)
- `lib/` **not** excluded from Rector (for consistency in migration)
- `vendor/` always excluded from analysis tools

### Configuration Files
- `phpstan.neon.dist` — Base static analysis config (extends `phpstan-baseline.neon`)
- `phpstan-baseline.neon` — Known issues (auto-generated; commit to track progress)
- `.php-cs-fixer.dist.php` — Code formatting rules
- `rector.php` — Automated refactoring rules
- `phpunit.xml` — Test runner config with strict settings (fail on warning, risky tests, coverage)

## Quick Workflow

1. **Before making changes**: Run `composer cs-fix` to ensure consistent formatting
2. **During development**: Use `composer test` to catch regressions early
3. **Before committing**: Run full quality suite:
   ```bash
   composer analyse && composer test && composer cs-fix
   ```
4. **For modernization tasks**: Use `composer refactor` to apply safe transformations

## Important Notes

- **PHP Minimum**: 7.4.0 (see `composer.json` `require.php`)
- **ZIP handling**: Library auto-detects PHP `ZipArchive` availability; falls back to PclZip
- **ODT Format**: Standard OpenDocument format; any office suite supporting ODT can open/inspect files
- **Configuration**: Passed to `Odf` constructor; defaults are sensible for most use cases
