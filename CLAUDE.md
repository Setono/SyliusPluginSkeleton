# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Setono Sylius Plugin Skeleton — a template for creating Sylius e-commerce plugins. PHP 8.2+, Symfony 6.4/7.2, Sylius 2.2+. After cloning, run `php init` to interactively rename all placeholder names (Acme/Example) to your vendor/plugin names.

## Commands

```bash
# Install dependencies
composer install

# Tests
composer phpunit                          # run all tests
vendor/bin/phpunit tests/path/ToTest.php  # run a single test file
vendor/bin/phpunit --filter testMethodName # run a single test method

# Static analysis
composer analyse                          # PHPStan (level max)

# Coding standards
composer check-style                      # ECS check (sylius-labs/coding-standard)
composer fix-style                        # ECS auto-fix

# Other quality tools
vendor/bin/rector process --dry-run       # check code modernization
vendor/bin/composer-dependency-analyser   # check dependency usage
vendor/bin/infection                      # mutation testing (requires 100% MSI)
composer validate --strict                # validate composer.json
composer normalize --dry-run              # check composer.json normalization
```

## Architecture

This is a Symfony Bundle structured as a Sylius plugin:

- `src/AcmeSyliusExamplePlugin.php` — Bundle class using `SyliusPluginTrait`
- `src/DependencyInjection/` — Extension (loads `config/services.xml`) and Configuration
- `config/services.xml` — Service definitions
- `config/routes.yaml` — Route loader, delegates to `config/routes/` (admin/shop contexts)
- `templates/` — Twig templates
- `translations/` — Translation files
- `tests/Application/` — Full Symfony/Sylius app used for integration testing (Kernel, config, database)
- `tests/DependencyInjection/` — Unit tests for the DI extension

## Code Style & Conventions

- `declare(strict_types=1)` on every PHP file
- Classes are `final` by default
- Full type declarations on properties, parameters, and return types
- Style enforced by ECS using `sylius-labs/coding-standard`
- Rector enforces PHP 8.2 level modernization
- PHPStan runs at level max with Symfony and Doctrine integration
- Bundle naming follows `{Vendor}Sylius{Name}Plugin` convention
- DI extension alias uses snake_case (e.g., `acme_sylius_example`)

## Translations

Plugins built from this skeleton should be translated into the following locales (source locale is English):

- Nordic: Danish (`da`), Swedish (`sv`), Norwegian (`no`), Finnish (`fi`)
- Large EU: German (`de`), French (`fr`), Spanish (`es`), Italian (`it`), Dutch (`nl`), Polish (`pl`)
- Other common Sylius locales: Portuguese (`pt`), Czech (`cs`), Hungarian (`hu`), Russian (`ru`), Ukrainian (`uk`)

Translation files live in `translations/` and follow Symfony's `<domain>.<locale>.<format>` naming.

## CI Matrix

CI tests against PHP 8.2 + 8.3, Symfony 6.4 + 7.2, lowest + highest deps. Jobs: coding standards, dependency analysis, Psalm, PHPUnit, integration tests (MySQL + Doctrine schema validation), mutation tests, code coverage.
