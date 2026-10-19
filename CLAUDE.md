# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Setono Sylius Plugin Skeleton — a template for creating Sylius e-commerce plugins. PHP 8.2+, Symfony 6.4/7.4, Sylius 2.2+. After cloning, run `php init` to interactively rename all placeholder names (Acme/Example) to your vendor/plugin names. Plugins generated from the skeleton inherit this file, so everything below applies to them as well.

## Commands

```bash
# Install dependencies
composer install

# Tests
composer phpunit                             # run all tests
vendor/bin/phpunit --testsuite=unit          # unit tests only (no kernel, no database)
vendor/bin/phpunit --testsuite=functional    # functional tests (boot tests/Application)
vendor/bin/phpunit tests/path/ToTest.php     # run a single test file
vendor/bin/phpunit --filter it_does_something # run a single test method

# Static analysis
composer analyse                             # PHPStan (level max)

# Coding standards
composer check-style                         # ECS check (sylius-labs/coding-standard)
composer fix-style                           # ECS auto-fix

# Other quality tools
vendor/bin/rector process --dry-run          # check code modernization
vendor/bin/composer-dependency-analyser      # check dependency usage
vendor/bin/infection                         # mutation testing
symfony lsp:check                            # Symfony-aware diagnostics for the plugin and its test app (needs Symfony CLI >= 5.20)
composer validate --strict                   # validate composer.json
composer normalize --dry-run                 # check composer.json normalization

# Test application (run from the repository root)
tests/Application/bin/console <command>
(cd tests/Application && yarn install && yarn build)   # build assets
(cd tests/Application && symfony server:status)         # check before starting a second server
(cd tests/Application && symfony serve -d)              # serve the test app
```

The Sylius admin of the test application is at `/admin` with username `sylius` and password `sylius`.

## Architecture

This is a Symfony bundle structured as a Sylius 2 plugin, with `config/`, `templates/` and `translations/` at the repository root:

- `src/AcmeSyliusExamplePlugin.php` — Bundle class using `SyliusPluginTrait`; `getPath()` returns the repository root
- `src/DependencyInjection/` — Extension (loads `config/services.php`) and Configuration
- `config/services.php` — Service definitions
- `config/routes.yaml` / `config/routes_no_locale.yaml` — Route loaders, delegating to `config/routes/` (admin/shop contexts)
- `templates/` — Twig templates
- `translations/` — Translation files
- `tests/Unit/` — Unit tests (e.g. `tests/Unit/DependencyInjection/` for the DI extension)
- `tests/Functional/` — Functional tests that boot the test application
- `tests/Application/` — Full Symfony/Sylius app used for functional testing (Kernel, config, database)
- `tests/PHPStan/` — Loaders PHPStan uses to boot the test application
- `.symfony-lsp.json` — Points `symfony lsp:check` at the repository root and names the test application's kernel

## Workflow

- Never commit or push unless explicitly asked. A request to commit applies to that change only, not to later ones.
- Before each commit, run `composer fix-style`, `composer analyse` and `composer phpunit` and fix what they flag. Don't commit on top of failures, and never weaken a gate to get green: no PHPStan baselines, no lowered thresholds, no `--no-verify`.
- Branches mirror Sylius versions (`2.2.x`, …). Pull requests target the matching version branch, so always pass `--base` to `gh pr create`.
- Merge pull requests with a merge commit (`gh pr merge --merge --delete-branch`), not a squash.
- Commit subjects are imperative, sentence case and describe the outcome ("Require gedmo/doctrine-extensions ^3.22.2"), without Conventional Commit prefixes. The body explains why and links upstream issues or PRs.
- When behaviour that users see changes (new features, configuration keys, removed UI), update `README.md` to describe the current state. The plugin ships no Flex recipe, so the README documents every installation step.
- When a change requires plugin users to act during an upgrade (renamed or removed classes, services, templates or config keys, changed defaults), document it in `UPGRADE.md` under the relevant "Upgrading from X to Y" section.
- Keep this file current. When you install a tool, add a dependency that changes how things are done, or establish a new convention, update CLAUDE.md in the same change.
- Use relative paths in shell commands; absolute paths inside the working directory trigger permission prompts.
- Shell tools: `fd` to find files, `rg` to search text, `ast-grep` for syntax-aware code search, `jq` for JSON (never Python), `yq` for YAML and XML.

## Add when needed

Install these the first time a task needs them, then update this file: move the entry into the section it belongs to, and add its commands to [Commands](#commands).

- **Browser tests:** install [playwright-php/playwright-symfony](https://github.com/playwright-php/playwright-symfony) and use it rather than Panther or a JavaScript Playwright suite.
  - Run `composer require --dev playwright-php/playwright-symfony`, then `vendor/bin/playwright-install --browsers`.
  - Register `Playwright\Symfony\PlaywrightSymfonyBundle::class => ['test' => true]` in `tests/Application/config/bundles.php`.
  - Tests extend `Playwright\Symfony\Test\PlaywrightTestCase`. Put them in `tests/Browser/` with a `browser` testsuite in `phpunit.xml.dist`.
  - Specs discover their subjects (products, customers) from the fixtures instead of hardcoding ids. Elements they need get hooks through `{{ sylius_test_html_attribute('name', value) }}`, never a raw `data-test-*` attribute.
  - Make sure CI runs the suite. The `setono/sylius-plugin` actions don't install Playwright browsers yet.
- **Database writes in tests:** install `dama/doctrine-test-bundle` so each test runs in a transaction that is rolled back.
- **Doctrine in services:** require `setono/doctrine-orm-trait`. Inject `ManagerRegistry` and use `Setono\Doctrine\ORMTrait` instead of injecting `EntityManagerInterface`.
- **Composite services** (several implementations behind one interface, collected by tag): require `setono/composite-compiler-pass` and register its compiler pass in the bundle's `build()`.

## Code Style & Design

- `declare(strict_types=1)` on every PHP file.
- Classes are `final` by default; stateless services are `final readonly`. Resource models, repositories and other deliberate extension points are not final.
- Full type declarations on properties, parameters and return types. Use constructor property promotion and native enums instead of sets of constants.
- Every service has an interface next to its implementation (`FooInterface` and `Foo`), and consumers depend on the interface. That makes every plugin service replaceable through its interface alias.
- Extend behaviour through decorators and composites (a new implementation tagged into the composite), not by adding conditionals to existing classes.
- Use `Webmozart\Assert\Assert` for runtime assertions, never PHP's native `assert()`.
- Plain data holders (DTOs, messages, events) may use public properties instead of getters and setters.
- Services that memoize per request implement `Symfony\Contracts\Service\ResetInterface` and are tagged `kernel.reset`, so long-running workers don't leak state.
- Comments explain why, not what. Don't write docblocks that only repeat the signature. Never commit debug calls (`dump`, `dd`, `var_dump`, `print_r`, `exit`).
- Follow clean code and SOLID principles: small classes with one responsibility, composition over inheritance, meaningful names.
- Style is enforced by ECS using `sylius-labs/coding-standard`; Rector enforces PHP 8.2 level modernization. Code must run on the PHP floor in `composer.json` (8.2).
- Bundle naming follows the `{Vendor}Sylius{Name}Plugin` convention, and the DI extension alias uses snake_case (e.g., `acme_sylius_example`).

## Symfony

- **Service config:**
  - Services are configured in PHP. `config/services.php` imports one file per concern from `config/services/` (e.g. `config/services/provider.php`). Each file starts with `namespace Symfony\Component\DependencyInjection\Loader\Configurator;`.
  - Wire services explicitly with `service()` and `param()`. No autowiring, no autoconfiguration and no resource-based discovery in the plugin. The test application may autowire.
  - Service ids are the fully qualified class name, and the interface is registered as an alias of it. The ids Sylius generates for resources (`acme_sylius_example.factory.<resource>`) are the exception.
  - Decorate with an explicit priority and `service('.inner')`.
  - Call `registerForAutoconfiguration()` in the extension for the plugin's own extension-point interfaces, so implementations in the application get tagged.
- **Configuring other bundles:** do it in the extension's `prepend()`, with the configuration inlined as PHP arrays passed to `prependExtensionConfig()`. That covers grids, Twig hooks, workflows and Messenger buses. Don't ship YAML that applications have to import, and don't read YAML from disk in `prepend()`.
- **Classes:**
  - Controllers are single-action invokable `*Action` classes.
  - Twig extensions only declare functions and filters. The work happens in a lazy runtime (`[FooRuntime::class, 'method']`, tagged `twig.runtime`).
  - Code that reacts to events lives in event subscribers (`src/EventSubscriber/`); Doctrine listeners go in `src/EventListener/Doctrine/`.
- **Messenger:** move slow work to Messenger (`Message/Command/*`, `Message/Handler/*`). Messages carry ids rather than entities, and never secrets or personal data.
- **Workflows:** a `*Workflow` class holds the state and transition constants and a static config that the extension prepends into `framework.workflows`.
- **Validation and serialization** mapping lives in XML under `config/validation/` and `config/serialization/`, not in attributes or form options.
- **Routes:**
  - Routes are YAML, named `acme_sylius_example_<section>_<resource>_<action>`.
  - Shop routes are prefixed with `/{_locale}`.
  - Every file under `config/routes/` is imported from both `config/routes.yaml` and `config/routes_no_locale.yaml`.
- **Files:** YAML files use the `.yaml` extension.

## Sylius

- **Resources:** when the plugin adds resources, extend `AbstractResourceBundle` and override `getConfigFilesPath()` to point at `config/doctrine/model`. Register the resources from a `resources` node in the configuration tree, using `registerResources()` in the extension.
- **Factories:** never `new` a resource. Use the resource factory, and decorate `acme_sylius_example.factory.<resource>` when creation needs defaults.
- **Grids** are prepended as PHP arrays. The plugin bundle must therefore be registered before `SyliusGridBundle` in `bundles.php`, and the README must say so.
- **Templates:**
  - Admin and shop templates are injected through Twig hooks, prepended under `sylius_twig_hooks`.
  - Admin menu entries come from a listener on `sylius.menu.admin.main`.
  - Template directories and file names are snake_case.
  - The shop UI uses Bootstrap 5, `ux_icon('tabler:…')` icons and plain JavaScript in `public/`, with no build step.
- **Extending core entities:** ship an interface plus a trait (the trait carries `#[ORM\…]` attributes) and document in the README how to add them to the application's entity.

## Doctrine

- Plugin models are mapped in XML (`config/doctrine/model/*.orm.xml`) as `<mapped-superclass>`. Table names are `acme_sylius_example__<entity>`.
- Plugins don't ship migrations; those belong to the application.
- After mapping changes, run `tests/Application/bin/console doctrine:schema:validate`. CI validates the schema against MySQL.

## Testing

- **Every change ships with tests.**
  - Unit tests by default. Add functional tests when behaviour can only be seen with the kernel or the database. When a change has both surfaces (a service and an admin page), cover both.
  - Test both the happy path and the edge cases.
  - Assert values and outcomes, not just that methods were called, so mutation testing can kill mutants.
- **Layout:** `tests/Unit/` mirrors `src/` and never boots the kernel or touches the database. `tests/Functional/` boots `tests/Application` and runs against MySQL, with fixtures loaded in CI.
- **Conventions:**
  - Mark tests with the `#[Test]` attribute and name them in snake_case BDD style: `it_does_something` or `it_should_do_something_when_condition_is_met`.
  - Use `self::assert…()`. Data providers are `public static`.
  - **Use Prophecy for all test doubles** (`ProphecyTrait` and `$this->prophesize()`), never PHPUnit's `createMock()` or `createStub()`.
- **What to use for each kind of class:**
  - DI extension: `Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase`.
  - Configuration: `Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait`.
  - Kernel and web tests: call `restore_exception_handler()` in `tearDown()`.
  - Constraint validators: `ConstraintValidatorTestCase`.
- **Console commands:** follow https://symfony.com/doc/current/console.html#testing-commands.
  - **Functional tests:** extend `KernelTestCase` and create `new Application(self::$kernel)`, using the FrameworkBundle `Application`. Get the command with `$application->find('<command name>')` and run it with `CommandTester::execute()`.
  - **Unit tests:** construct the command with Prophecy doubles and pass it to `new CommandTester($command)`.
  - **Arguments and options:**
    - Pass options with two dashes, e.g. `'--dry-run' => true`. `InputOption::VALUE_NONE` options take `true`, and array options take an array.
    - Answer interactive questions with `$commandTester->setInputs([...])`.
  - **Assertions:**
    - Use `$commandTester->assertCommandIsSuccessful()` and assert on `getDisplay()`.
    - Pass `['capture_stderr_separately' => true]` to `execute()` when testing stderr or `section()` output.
  - **Console events:** `CommandTester` doesn't dispatch them. To test events, or the application as a whole, use `ApplicationTester`, and call `$application->setAutoExit(false)` first.
  - **Symfony 8.1:** `KernelTestCase::runCommand()` and its `ExecutionResult` are the recommended way from Symfony 8.1. The plugin's Symfony floor is 6.4, so use `CommandTester` until that floor reaches 8.1.
- **Form types:** follow https://symfony.com/doc/current/form/unit_testing.html. Test the plugin's own form types; built-in and third-party types need no tests.
  - Extend `Symfony\Component\Form\Test\TypeTestCase`. Create forms through `$this->factory->create(FooType::class, $model)`, never by instantiating the type.
  - Start with a test that the type compiles: class inheritance, `buildForm()` and option resolution.
  - Submit with `$form->submit($formData)` and assert `$form->isSynchronized()`, which is false only when a data transformer failed. Then compare the model with an `$expected` object built from `$formData`.
  - Test custom view variables through `createView()` and `$view->vars`.
  - Cover several form conditions with a data provider rather than copies of the same test.
  - **Types with constructor dependencies:**
    - Create the doubles with Prophecy in `setUp()` before calling `parent::setUp()`, because that call builds the factory from `getExtensions()`.
    - Return the type instance from `getExtensions()` in a `PreloadedExtension`.
  - **Other extensions:**
    - Only the `CoreExtension` is loaded by default. Return other extensions from `getExtensions()`, e.g. `ValidatorExtension` when the type uses validator options such as `invalid_message`; otherwise the test fails with an `InvalidOptionsException`.
    - Return additional types, type extensions and type guessers from `getTypes()`, `getTypeExtensions()` and `getTypeGuessers()`.
  - **`EntityType`:** types that use it need the `DoctrineOrmExtension` with a `ManagerRegistry` double. When that isn't practical, extend `KernelTestCase` and create the form with the `form.factory` service.
  - **Validation:** don't test it in form tests. It's applied by a listener that isn't active in the test case, and it depends on the validation configuration. Test the constraints directly with `ConstraintValidatorTestCase`.
  - **Form themes:** extend `Symfony\Bridge\Twig\Test\FormLayoutTestCase`.
- **UI verification:**
  - Verify every UI change (templates, Twig hooks, forms, grids, flash messages, styling) in the running test application with the Playwright MCP (`browser_navigate`, `browser_snapshot`, `browser_take_screenshot`). Check both what renders and how it behaves, e.g. submit the form.
  - PHPUnit, PHPStan and ECS don't catch broken hooks, missing JavaScript or layout regressions.
  - Use the MCP while developing. Durable coverage belongs in browser tests (see [Add when needed](#add-when-needed)).

## Quality Gates

- **PHPStan** runs at level max with the Symfony, Doctrine, PHPUnit and Prophecy extensions, without a baseline. Fix errors rather than ignore them. When an ignore is unavoidable, scope it to the error identifier and the line, with a comment saying why.
- **Rector** `--dry-run` must be clean; CI fails otherwise.
- **Code coverage** must be at least 90%, for the project and for the lines each change adds or modifies (`codecov.yml`). Like the Infection thresholds, the targets only go up and have no tolerance below them. Check coverage locally with `vendor/bin/phpunit --coverage-text` (needs pcov or Xdebug).
- **Infection** thresholds only go up. The skeleton starts at 100% MSI. When tests raise the score, raise `minMsi`/`minCoveredMsi` in the same change, and never lower them to get a change through.
- **composer-dependency-analyser** runs with `require-dev` removed. Every package used in `src/` or `config/` must be in `require`, and every ignore needs a comment explaining why.
- **Dev-only version pins:**
  - They go in `require-dev`, never in `conflict`, so they don't affect projects installing the plugin.
  - Prefer a floor on the fixed package (e.g. `gedmo/doctrine-extensions: ^3.22.2`) over holding back other packages.
  - The commit explains why the pin exists and when it can go.
- **Lowest dependencies:** before pushing dependency changes, sanity-check them with `composer update --prefer-lowest && composer analyse && composer phpunit`.
- **Composer:** run `composer normalize` after `composer require`. `composer validate --strict` must pass.

## Translations

Plugins built from this skeleton should be translated into the following locales (source locale is English):

- Nordic: Danish (`da`), Swedish (`sv`), Norwegian (`no`), Finnish (`fi`)
- Large EU: German (`de`), French (`fr`), Spanish (`es`), Italian (`it`), Dutch (`nl`), Polish (`pl`)
- Other common Sylius locales: Portuguese (`pt`), Czech (`cs`), Hungarian (`hu`), Romanian (`ro`), Ukrainian (`uk`)

Translation files live in `translations/` and follow Symfony's `<domain>.<locale>.yaml` naming, in the `messages`, `flashes` and `validators` domains. Keys are namespaced by the extension alias (`acme_sylius_example.ui.*`, `acme_sylius_example.form.*`).

Whenever you add, rename or remove a key, do it in every locale file in the same change, not just `en`. Keep brand names and `{{ placeholders }}` untranslated, and use `no` (not `nb`) for Norwegian.

## CI

CI uses the `setono/sylius-plugin/*@v2` composite actions. Most jobs run a matrix of PHP 8.2/8.3/8.4 × Symfony ~6.4/~7.4 × lowest/highest dependencies. The jobs are:

- **Backwards compatibility:** Roave BC check, pull requests only.
- **Coding standards:** composer validate/normalize, ECS, Rector, `lint:yaml`, `lint:twig`, and `symfony lsp:check` (because `.symfony-lsp.json` exists).
- **Dependency analysis.**
- **Static code analysis:** PHPStan.
- **Unit tests:** the `unit` suite.
- **Functional tests:** highest dependencies only. Builds the assets, creates the MySQL schema, runs `doctrine:schema:validate`, loads the fixtures and runs the `functional` suite.
- **Mutation tests:** Infection.
- **Code coverage:** uploads the PHPUnit coverage to Codecov. Codecov's project and patch statuses fail below 90%.
