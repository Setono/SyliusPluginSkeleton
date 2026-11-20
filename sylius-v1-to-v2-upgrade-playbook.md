# Sylius v1 → v2 Plugin Upgrade Playbook

A generic, reusable checklist for taking a Sylius 1.x plugin to Sylius 2.x. Each section lists the change, why it matters, and where it shows up in the codebase you'll touch.

Use this as a punch list. Skip what doesn't apply, but read every section before deciding — several steps interact (e.g. plugin file layout interacts with bundle `getPath()` overrides, which interacts with Doctrine mapping discovery).

## Reference repository — start here when in doubt

[`Setono/SyliusPluginSkeleton`](https://github.com/Setono/SyliusPluginSkeleton) is the canonical reference for what a Sylius 2.x plugin should look like. Mirror it when this playbook is silent or ambiguous. Look at it specifically for:

* `tests/Application/Kernel.php` and `tests/Application/config/bundles.php`
* `tests/Application/config/packages/*.yaml`
* `phpunit.xml.dist`, `phpstan.neon`, `ecs.php`, `rector.php`, `composer-dependency-analyser.php`, `infection.json5`
* `.github/workflows/build.yaml`
* Bundle `getPath()` override
* The `composer.json` `require-dev` block (including the `setono/sylius-plugin: ^2.0` dev-dep), `scripts` block and `config` block
* `CLAUDE.md`, which plugins generated from the skeleton inherit

**Where this playbook and the skeleton diverge, the playbook calls it out explicitly**, and that guidance applies. Everywhere else, the skeleton and its `CLAUDE.md` are the source of truth, because CI runs against them: if a snippet here disagrees with the skeleton without saying so, follow the skeleton and report the mismatch in `Setono/SyliusPluginSkeleton`.

---

## 1. Version constraints

Bump `composer.json` to the new floor. The Sylius 2.x ecosystem requires modern PHP and Symfony.

The `require` block below is an example: list the packages your plugin's own code uses (`composer-dependency-analyser` checks that, see §10), with the skeleton's floors. The `require-dev` block is the skeleton's. Copy it from the skeleton's `composer.json` (it may have moved on since this was written), then add what your plugin's tests need on top.

```json
"require": {
    "php": ">=8.2",
    "sylius/channel": "^2.2",
    "sylius/channel-bundle": "^2.2",
    "sylius/core-bundle": "^2.2",
    "sylius/product": "^2.2",
    "sylius/resource-bundle": "^1.12",
    "sylius/taxonomy": "^2.2",
    "sylius/ui-bundle": "^2.2",
    "symfony/config":             "^6.4 || ^7.4",
    "symfony/console":            "^6.4 || ^7.4",
    "symfony/dependency-injection":"^6.4 || ^7.4",
    "symfony/event-dispatcher":   "^6.4 || ^7.4",
    "symfony/form":               "^6.4 || ^7.4",
    "symfony/http-foundation":    "^6.4 || ^7.4",
    "symfony/http-kernel":        "^6.4 || ^7.4",
    "symfony/routing":            "^6.4 || ^7.4",
    "symfony/validator":          "^6.4 || ^7.4",
    "twig/twig": "^3.0"
},
"require-dev": {
    "api-platform/doctrine-orm": "^4.3",
    "api-platform/symfony": "^4.3.3",
    "gedmo/doctrine-extensions": "^3.22.2",
    "knplabs/knp-menu-bundle": "^3.4.1",
    "payum/core": "^1.7.5",
    "setono/sylius-plugin": "^2.0",
    "sylius/grid-bundle": "^1.15",
    "sylius/sylius": "~2.2.10",
    "symfony/debug-bundle": "^6.4 || ^7.4",
    "symfony/dotenv": "^6.4 || ^7.4",
    "symfony/intl": "^6.4 || ^7.4",
    "symfony/property-info": "^6.4 || ^7.4",
    "symfony/serializer": "^6.4 || ^7.4",
    "symfony/var-exporter": "^6.4 || ^7.4",
    "symfony/web-profiler-bundle": "^6.4 || ^7.4",
    "symfony/webpack-encore-bundle": "^2.2"
}
```

Notes:

* Drop PHP 7.x and 8.0/8.1 support entirely. PHP `>= 8.2` is the floor.
* **Sylius 2.3 requires PHP `^8.3`** (Sylius 2.2 requires `^8.2`). A plugin that keeps the PHP 8.2 floor must keep allowing Sylius 2.2, and its PHP 8.2 CI lanes can only ever resolve Sylius 2.2.x. Raise the PHP floor to 8.3 in the same change that raises the Sylius floor to 2.3.
* Drop Symfony `< 6.4`. Target the `6.4` and `7.4` LTS releases (`^6.4 || ^7.4` excludes 7.0–7.3).
* Floor the Sylius packages at the minor the branch targets (`^2.2` on `2.2.x`, like the skeleton's `sylius/core-bundle`). The test app only runs that minor, but the static-code-analysis and dependency-analysis jobs install without `sylius/sylius`, so on lowest dependencies they resolve exactly that floor.
* Pin `sylius/sylius` to the skeleton's `~2.x.y` patch range in `require-dev` to keep the test app reproducible. On `2.2.x` that's `~2.2.10`, the first release that conflicts with `doctrine/orm >= 3.7`, which rejects Sylius' `ShipmentUnit#shipment` mapping and fails `doctrine:schema:validate`.
* Don't require `api-platform/core` or `lexik/jwt-authentication-bundle`. Sylius 2.2 uses API Platform's split packages (`api-platform/symfony`, `api-platform/doctrine-orm`, …), and `sylius/sylius` already requires `lexik/jwt-authentication-bundle: ^3.1`.
* Several `require-dev` entries are floors that keep the test app booting, mostly on lowest dependencies. Keep them until the skeleton drops them:
  * `api-platform/doctrine-orm: ^4.3` — `api-platform/symfony` 4.3 wires a class that `api-platform/doctrine-orm` only ships from 4.3, and nothing enforces the pairing.
  * `gedmo/doctrine-extensions: ^3.22.2` — older versions declare a template bound that `symfony/type-info` >= 7.4.6 rejects, which breaks loading the API routes.
  * `knplabs/knp-menu-bundle: ^3.4.1` — 3.4.0 doesn't register the `@KnpMenu` Twig namespace, so every admin page fails.
  * `payum/core: ^1.7.5` — older versions aren't compatible with `psr/log` 3.
  * `symfony/var-exporter: ^6.4 || ^7.4` — on PHP 8.4+ Composer otherwise resolves var-exporter 8, which has no LazyGhost, and every Doctrine command in the test app fails with "Symfony LazyGhost is not available". CI doesn't catch it, because it holds every Symfony package to one minor through `SYMFONY_REQUIRE`.
* Pull dev tooling from `setono/sylius-plugin: ^2.0` (PHPStan, PHPUnit, Rector, ECS, Infection presets, GitHub composite actions, `shipmonk/composer-dependency-analyser`). This is the **single replacement** for the old `setono/code-quality-pack`.
* Allow modern `doctrine/persistence` (`^4.0`) and other libs that bumped to support Symfony 7.

Document the new floor in `UPGRADE.md`:

```
### Requirements
- PHP `>= 8.2`
- Symfony `6.4` or `7.4`
- Sylius `2.2` or newer

The plugin no longer supports Sylius 1.x or PHP `< 8.2`.
```

---

## 2. Plugin file layout (Sylius 2.x skeleton)

Sylius 2.x moved away from the `Resources/{config,translations,views}` convention. Mirror that in your plugin:

| Sylius 1.x layout | Sylius 2.x layout |
| --- | --- |
| `src/Resources/config/` | `config/` |
| `src/Resources/translations/` | `translations/` |
| `src/Resources/views/` | `templates/` |

Two consequences for the bundle class (extends `AbstractResourceBundle`). Both are required — overriding `getPath()` alone breaks Doctrine mapping discovery with `File mapping drivers must have a valid directory path, however the given path [.../Resources/config/doctrine/model] seems to be incorrect!`, because `AbstractResourceBundle::getConfigFilesPath()` returns `getPath().'/Resources/config/doctrine/<format>'` by default.

```php
final class YourPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    public function getPath(): string
    {
        // points bundle-relative paths (@YourPlugin/...) at the repo root,
        // not src/
        return \dirname(\__DIR__);
    }

    protected function getConfigFilesPath(): string
    {
        // tells Sylius's Doctrine mapping loader where the XML lives now
        return sprintf(
            '%s/config/doctrine/%s',
            $this->getPath(),
            strtolower($this->getDoctrineMappingDirectory()),
        );
    }
}
```

Document the move in `UPGRADE.md` so consumers who override templates know where to look:

```
### Plugin file layout aligned with the Sylius 2.x plugin skeleton

`Resources/{config,translations,views}` was moved to the repository root:

| 2.x                        | 3.0           |
| -------------------------- | ------------- |
| `Resources/config/`        | `config/`     |
| `Resources/translations/`  | `translations/` |
| `Resources/views/`         | `templates/`  |
```

---

## 3. DI configuration: XML → PHP DSL

**Use PHP for service configuration. Do not ship XML.** This is the Setono Sylius-2 convention, and the plugin skeleton already follows it (`config/services.php` loaded via `PhpFileLoader`).

Why PHP over XML:

* IDE navigation and refactoring work — jump-to-definition on `service(Foo::class)`, rename a class and the references update.
* PHPStan can analyze it. Typos in service ids, missing classes, and wrong argument shapes fail at compile time instead of at runtime.
* The config lives in the same language as the code that depends on it.
* No XML schema to remember.

Convert all DI service files from XML to PHP `ContainerConfigurator` closures.

`config/services.php` is the entry point loaded by your extension via `PhpFileLoader`. Each leaf file lives under `config/services/<topic>.php` and starts with:

```php
<?php
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(YourService::class)
        ->args([service(Collaborator::class), param('your_plugin.option')])
        ->tag('kernel.event_subscriber');
};
```

Why declare `namespace Symfony\Component\DependencyInjection\Loader\Configurator;` at the top? It lets you use `service()`, `param()`, `inline_service()`, etc. as bare function calls without `use function` imports — the closure is invoked inside that namespace by the loader.

The extension loads it like this:

```php
public function load(array $configs, ContainerBuilder $container): void
{
    $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
    $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

    // …setParameter() calls…

    $loader->load('services.php');

    $this->registerResources('your_plugin', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);
}
```

Document for consumers who imported your XML files directly:

| Removed | Replacement |
| --- | --- |
| All XML files under `config/services/` (and `config/services.xml`) | Replaced by PHP DSL equivalents (`config/services.php` + `config/services/*.php`) loaded via `PhpFileLoader`. Same service ids, arguments, tags, decorators, and aliases. |

---

## 4. Service ids: snake_case aliases → FQCN

Sylius 2.x and modern Symfony favor FQCN service ids; they autowire cleanly and consumers can decorate by class name. Convert every service id you control:

```php
// before:
$services->set('your_plugin.resolver.something', SomethingResolver::class);

// after:
$services->set(SomethingResolver::class);
$services->alias(SomethingResolverInterface::class, SomethingResolver::class); // forward-compatible
```

Rules:

* Use FQCN as the id for everything you register.
* Keep an alias from the `*Interface` to the FQCN for forward compatibility — consumers should fetch by interface.
* **Do not rename** Sylius-resource-bundle-managed ids (`your_plugin.factory.foo`, `repository.foo`, `manager.foo`) — those are auto-generated from the resource config.
* **Leave existing snake-cased ids in unrelated parts of the codebase as-is** unless the surrounding work touches them. Don't bulk-rename for the sake of renaming.

Document the renames as a table in `UPGRADE.md` because anyone fetching from the container by id will break.

### Sylius 2.x service-id renames you'll hit

Sylius itself renamed several services in 2.x. If your plugin injects any by id, update the references. Known cases (extend this list as you find more):

| Sylius 1.x id | Sylius 2.x id |
| --- | --- |
| `sylius.integer_distributor` | `sylius.distributor.integer` |

CLAUDE.md guidance to add:

```
- All new services must be registered using their FQCN as the service id
  (e.g. `id="App\Validator\Constraints\FooValidator"`), not a snake-cased alias
  like `your_plugin.validator.foo`. This matches Symfony's autowiring conventions
  and lets consumers override or decorate by class name. The plugin still has
  older snake-cased ids — leave those as-is unless the surrounding work touches
  them, but don't add any new ones.
```

---

## 5. Configuring other bundles: `prepend()` instead of YAML, PHP classes for grids

In Sylius 1.x plugins, you'd ship `config/app/config.yaml` and ask consumers to `imports:` it. In 2.x, prepend the config from your bundle extension and remove the YAML file entirely. Admin grids are the exception: ship them as PHP grid classes (see [Admin grids](#admin-grids-php-grid-classes-not-prepended-arrays) below).

```php
final class YourExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_twig_hooks', [/* … */]);
        $container->prependExtensionConfig('doctrine',           [/* … */]);
    }
}
```

Inline the array literal directly in `prepend()` — don't keep YAML on disk and parse it. The integration point becomes greppable and there's no I/O at compile time.

### Prepended config adds; it can't override what Sylius sets

`prependExtensionConfig()` puts the plugin's array at the front of the receiving bundle's config list (`array_unshift` in `ContainerBuilder`), so it has the lowest precedence. Everything the application loads is merged after it, including the Sylius config files that `config/packages/_sylius.yaml` imports. New keys work: a new hookable, email or workflow. A value Sylius already sets beats the plugin's, and nothing reports it:

* Changing the template or priority of a hookable Sylius defines, or the template of a Sylius email (`sylius_mailer.emails.order_confirmation.template`), has no effect.
* Disabling a Sylius hookable with `enabled: false` has no effect either. The twig-hooks configuration derives each hookable's `type` per config fragment, and Sylius' later fragment, which sets `template`, turns it back into a template hookable. Verified on Sylius 2.2.13: prepending `enabled: false` for `terms_and_conditions` under `sylius_shop.base.footer.content.menu.informational_links` leaves the placeholder footer link enabled.

To change what Sylius configures, use one of these instead:

* **Application config.** Document the YAML in the README. The application's own files under `config/packages/` load after `_sylius.yaml`, so they win. This suits changes that are the merchant's choice, such as hiding Sylius' placeholder footer links.
* **A compiler pass** in the plugin bundle's `build()` that edits the definition Sylius registered. A hookable is the service `sylius_twig_hooks.hook.<hook name>.hookable.<hookable name>`. To disable it, set its class to `Sylius\TwigHooks\Hookable\DisabledHookable` and its arguments to `[<hook name>, <hookable name>]`. A compiler pass also beats the application's config, so put it behind an option in the plugin's configuration when applications may want the original back.
* **A runtime extension point.** For emails, a service tagged `sylius_mailer.email_modifier` that implements `Sylius\Component\Mailer\Modifier\EmailModifierInterface` can change the template for one email code. Decorating `sylius.email_sender` also works.

### Prepended config and the plugin's own parameters

Symfony resolves the `%parameters%` in each bundle's configuration when that bundle's extension loads, in `bundles.php` order (`MergeExtensionConfigurationPass`). The plugin's own parameters, such as `%your_plugin.model.thing.class%` from `registerResources()`, only exist once the plugin's `load()` has run. Prepended config that references them breaks the container when the receiving bundle is registered before the plugin:

```
You have requested a non-existent parameter "your_plugin.model.thing.class" while loading extension "sylius_grid".
```

* Use the interface or class name instead, as Sylius' own workflows do (`supports: Sylius\Component\Core\Model\OrderInterface`). `FrameworkBundle` and `DoctrineBundle` sit at the top of every application's `bundles.php`, so `framework` (workflows, Messenger) and `doctrine` config can't use plugin parameters at all.
* For grids, ship a PHP grid class (next section). The grid bundle resolves its `%parameter%` resource class in a compiler pass, after every extension has loaded, so bundle order doesn't matter.
* When prepended config has to reference a plugin parameter, the plugin must be registered before the receiving bundle. Say so in the README's installation steps, and register the plugin in that position in `tests/Application/config/bundles.php`.

### Admin grids: PHP grid classes, not prepended arrays

From `sylius/grid-bundle` 1.16, grids defined as arrays under `sylius_grid.grids` (YAML, or prepended from `prepend()`) are deprecated: `ArrayGridProvider::get()` triggers a deprecation each time such a grid is used. Sylius 2.3 requires grid-bundle `^1.16`, and its `UPGRADE-2.3.md` calls PHP grids "the direction of Sylius 3.0". Ship each admin grid as an invokable class with `#[AsGrid]`:

```php
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

interface ThingGridInterface
{
    public const NAME = 'your_plugin_admin_thing';

    public function __invoke(GridBuilderInterface $gridBuilder): void;
}

#[AsGrid(resourceClass: '%your_plugin.model.thing.class%', name: self::NAME)]
final class ThingGrid implements ThingGridInterface
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->addField(StringField::create('code')->setLabel('sylius.ui.code')->setSortable(true))
            ->addFilter(StringFilter::create('code')->setLabel('sylius.ui.code'))
            ->withMainActions(CreateAction::create())
            ->withItemActions(UpdateAction::create(), DeleteAction::create())
        ;
    }
}
```

Register it explicitly, the way Sylius core registers its own grids:

```php
$services->set(ThingGrid::class)->tag('sylius.invokable_grid');
$services->alias(ThingGridInterface::class, ThingGrid::class);
```

* **Require `sylius/grid-bundle: ^1.16`** in `require`. It installs on Sylius 2.0–2.2, which require `^1.13` (the skeleton resolves 1.16.1 with Sylius 2.2.13). 1.16 is the first version that resolves a `%parameter%` resource class: its `InvokableGridPass` copies the attribute's `resourceClass` into a service definition, and the container resolves the parameter at compile time. On 1.14 and 1.15, `AbstractGrid` reads `#[AsGrid]` by reflection at runtime, so the placeholder stays a literal string. Use the parameter rather than `Thing::class`, so applications can override the model class.
* **Type-hint `Sylius\Component\Grid\Builder\GridBuilderInterface`.** grid-bundle 1.16 deprecates `Sylius\Bundle\GridBundle\Builder\GridBuilderInterface`, although Sylius 2.3's own grids still use it. The field, filter and action builders (`StringField`, `StringFilter`, `CreateAction`, …) stay in `Sylius\Bundle\GridBundle\Builder\…`.
* **Bundle order doesn't matter.** Verified on Sylius 2.2.13 with grid-bundle 1.16.1 and the plugin registered last: the grid resolves its resource class and triggers no deprecation.
* **Applications customise the grid with a grid mutator.** A mutator is a service that implements `Sylius\Component\Grid\Mutator\GridMutatorInterface`. In the application it's autoconfigured from `#[AsGridMutator(grid: ThingGridInterface::NAME)]`; without autoconfiguration, tag it `sylius.grid_mutator` with a `grid` attribute. Its `__invoke(GridBuilderInterface $gridBuilder)` adds, changes or removes fields, filters and actions. Mutators need grid-bundle 1.16 and apply whichever provider serves the grid. YAML doesn't merge into a PHP grid: on Sylius 2.2 the PHP grid wins and a YAML grid with the same name is ignored without a warning (`ServiceGridProvider` runs before `ArrayGridProvider`). On Sylius 2.3, `OverrideGridProvider` decorates the chain provider and, with the default `sylius_core.grid.use_legacy_config: true`, serves the YAML grid instead, which replaces the PHP grid wholesale.

### Tell consumers in `UPGRADE.md`

| `@YourPlugin/config/app/config.yaml` | Removed — the plugin's twig-hook configuration is now registered automatically via `YourExtension::prepend()`, and its grids are PHP classes. Drop the matching `imports:` entry from your `config/packages/your_plugin.yaml`. |
| YAML overrides of the `your_plugin_admin_thing` grid | The grid is now the PHP class `YourPlugin\Grid\ThingGrid`, and YAML no longer merges into it. Move your changes to a grid mutator: a service implementing `Sylius\Component\Grid\Mutator\GridMutatorInterface` with `#[AsGridMutator(grid: 'your_plugin_admin_thing')]`. |

CLAUDE.md guidance:

```
- When the plugin needs to configure another bundle (`sylius_twig_hooks`,
  doctrine, etc.), do it from `YourExtension::prepend()` via
  `$container->prependExtensionConfig(...)` with the config inlined as a
  PHP array. Don't ship YAML files that consumers have to import.
- Prepended config has the lowest precedence: it adds keys but can't change
  or disable what Sylius' own config sets. For that, document app config in
  the README, or use a compiler pass or a runtime extension point.
- Keep the plugin's own %parameters% out of prepended config. If you can't,
  the plugin must be registered before the receiving bundle in bundles.php,
  and the README must say so.
- Admin grids are `#[AsGrid]` PHP classes tagged `sylius.invokable_grid`,
  with `resourceClass: '%your_plugin.model.<resource>.class%'`. Require
  `sylius/grid-bundle: ^1.16`. Applications customise them with grid
  mutators.
```

---

## 6. Routing: align with Sylius 2.x admin path conventions

Sylius 2.x exposes `%sylius_admin.path_name%` (defaults to `admin`). **It's a test-app concern, not a plugin concern.** The test app uses the parameter when mounting the Sylius admin bundle so the admin path stays configurable. Plugins are free to mount their own admin routes under a literal `prefix: /admin` (this is what `Setono/SyliusPluginSkeleton`'s `routes_no_locale.yaml` does) **or** under `%sylius_admin.path_name%`.

Pick one approach and document it. Either works:

```yaml
# Option A — follows the configurable admin path
your_plugin_admin:
    resource: "@YourPlugin/config/routes/admin.yaml"
    prefix: /%sylius_admin.path_name%

# Option B — literal /admin prefix (matches the skeleton)
your_plugin_admin:
    resource: "@YourPlugin/config/routes/admin.yaml"
    prefix: /admin
```

Also: Sylius 2.x uses a sub-`/ajax` prefix for AJAX endpoints. Move route files under `config/routes/admin/` and split AJAX endpoints into `config/routes/admin/ajax.yaml` so they end up at `/admin/ajax/...`.

Tell consumers to update their import path and drop their own `prefix:` line:

| `@YourPlugin/config/admin_routing.yaml` | Replaced by `@YourPlugin/config/routes.yaml`. The new file already applies the `/%sylius_admin.path_name%` prefix, so the consumer no longer needs a `prefix:` line. |

If you renamed routes (e.g. moved to `/ajax`), document each rename — consumers generating URLs by name will silently break otherwise.

---

## 7. Templates: rebuild around Sylius Twig hooks

Sylius 2.x replaced direct template overriding with [Twig hooks](https://docs.sylius.com/the-customization-guide/customization/twig-hooks) on admin and shop pages. The admin main menu, the shop account menu and emails still work as in 1.x. What changes vs. what stays:

* **Template overrides** (`templates/bundles/SyliusAdminBundle/...`) — port to hook templates registered via `sylius_twig_hooks`.
* **Admin main-menu listeners** — **keep them**. Sylius 2.x still builds the admin sidebar with KnpMenu and dispatches `sylius.menu.admin.main` (`Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent`) once its own items are in. There is no Twig hook inside the menu tree. Sylius 2.3 builds the menu from services tagged `sylius_admin.main_menu_provider` (`Sylius\Bundle\AdminBundle\Menu\Provider\MenuProviderInterface`), but its last provider, `EventMenuProvider`, still dispatches the event, so listeners need no changes. Switch to a tagged provider only when the plugin requires Sylius 2.3. The shop account menu works the same way through `sylius.menu.shop.account`.
* **Page menu builders** (`ProductFormMenuBuilder`, `ProductUpdateMenuBuilder`, `ProductVariantFormMenuBuilder`, `OrderShowMenuBuilder`, `CustomerShowMenuBuilder`, `PromotionUpdateMenuBuilder`) — removed in 2.0, and nothing dispatches their events any more. Port listeners on them to hooks on that page, e.g. a tab under `sylius_admin.product.update.content.form.side_navigation`.
* **Emails** — no hooks. No Sylius 2 email template has a hookpoint, so emails are still customised by swapping the template. The application does that with `sylius_mailer.emails.<code>.template` or an override under `templates/bundles/`. A plugin can't change a core email's template from `prepend()`, because Sylius imports its own `sylius_mailer` config as application config, which wins over prepended config. A plugin instead swaps the template or subject in an email modifier (`Sylius\Component\Mailer\Modifier\EmailModifierInterface`, tagged `sylius_mailer.email_modifier`), changes the outgoing message in a `sylius.email_send.pre_send` listener, or decorates `sylius.email_sender`. Emails the plugin adds get their own code under `sylius_mailer.emails`, which `prepend()` can ship.
* **Form extensions (`AbstractTypeExtension`)** — **keep them**, and give every field they add a hookable that renders it. Sylius 2.x core itself still uses form extensions (e.g. `ProductVariantTypeExtension` adds the `channelPricings` field, and hookables render it in the admin variant form). The form extension still does the schema work, but the hookable is what outputs the field. Admin and shop templates end forms with `form_end(form, {render_rest: false})`, and the admin and shop form themes override `form_end` to output only the CSRF token, so nothing renders leftover fields. A field no hookable renders never reaches the page. If it carries a constraint (e.g. `IsTrue` on a checkbox), every submission fails with an error nobody sees. Don't rewrite working extensions as hook subscribers.

### Required bundle: `SyliusTwigHooksBundle`

Twig hooks come from the `sylius/twig-hooks` package, registered as `Sylius\TwigHooks\SyliusTwigHooksBundle` in `tests/Application/config/bundles.php`. The skeleton has it pre-registered. A test app without it fails loudly: the container doesn't compile, because the Sylius admin and shop config it imports declares hooks under `sylius_twig_hooks`, and any template that uses `{% hook %}` fails with `Unknown "hook" tag`.

### Canonical hook names (admin product form)

The Sylius 2 source is authoritative for hook names. For the admin product update/create form:

* **Hookpoints (Twig)**: `vendor/sylius/sylius/src/Sylius/Bundle/AdminBundle/templates/product/form/sections.html.twig` (`{% hook 'sections' %}`).
* **Hook declarations (YAML)**: `vendor/sylius/sylius/src/Sylius/Bundle/AdminBundle/Resources/config/app/twig_hooks/product/update.yaml`.

Common admin-product hooks:

* `sylius_admin.product.{update,create}.content.form.side_navigation` — side-nav tab button
* `sylius_admin.product.{update,create}.content.form.sections` — section panel

Inside a hook partial, pull `form` from `hookable_metadata.context.form`; gate visibility with `hookable_metadata.configuration.active`.

Tell consumers in `UPGRADE.md`:

```
### Template paths
The admin templates were rebuilt around Sylius Twig hooks. If you previously
overrode the 2.x templates directly, port your customizations to the matching
hook.
```

Add a CLAUDE.md tip on Twig extension/runtime split (general best practice that surfaces during this refactor):

```
- Twig extensions should split into an `Extension` (eagerly loaded, declares
  functions/filters) and a `Runtime` (lazily instantiated, holds dependencies
  and runs the logic). Wire functions via `[Runtime::class, 'method']` and tag
  the runtime service with `twig.runtime`.
```

---

## 7a. `CollectionType` → `LiveCollectionType` (admin forms only)

### Why this surfaces

In Sylius 1.x, the UI bundle's `collection_widget` form theme rendered `<a data-form-collection="add">` and admin jQuery cloned the prototype. In Sylius 2.x:

* Admin extends `bootstrap_5_layout.html.twig` directly, not the UI bundle theme.
* Admin assets ship no generic form-collection Stimulus controller.
* Sylius admin overrides each `CollectionType` field with `Symfony\UX\LiveComponent\Form\Type\LiveCollectionType` (see `Sylius\Bundle\AdminBundle\Form\Type\ProductType::buildForm()`'s `images` override — the canonical example).

Consequence: a `CollectionType` field migrated as-is to the v2 admin keeps its `data-prototype` attribute in the DOM but the Add/Delete buttons do nothing. No console error — just a non-functional UI.

### Migration recipe

**1. Switch `getParent()` to `LiveCollectionType`.** `symfony/ux-live-component` is already shipped via the skeleton.

```php
-use Symfony\Component\Form\Extension\Core\Type\CollectionType;
+use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

 final class FooCollectionType extends AbstractType
 {
     public function configureOptions(OptionsResolver $resolver): void
     {
         $resolver->setDefaults([
             'entry_type' => FooType::class,
+            'entry_options' => ['label' => false],
             'allow_add' => true,
             'allow_delete' => true,
             'by_reference' => false,
+            'block_name' => 'entry',
+            'button_add_options' => [
+                'label' => 'your_plugin.ui.add_foo',
+            ],
         ]);
     }

     public function getParent(): string
     {
-        return CollectionType::class;
+        return LiveCollectionType::class;
     }
 }
```

* `entry_options.label = false` suppresses the auto-generated numeric `<legend>` ("0 *", "1 *", …) on each row.
* `block_name = 'entry'` matches Sylius core's image-collection convention.
* `button_add_options.label` is the translation key for the Add button.

**2. Make the entry type's required scalar setters nullable-and-no-op-on-null.** When `LiveCollectionType` handles an `addCollectionItem` action, it re-binds the entire form server-side — _including_ the freshly-created empty entry, before the user has typed anything. The form binds each field with null-or-empty data, so non-nullable setters (`setQuantity(int $quantity)`) explode with `InvalidTypeException: Expected argument of type "int", "null" given`.

**Preferred fix — widen the model's setter signature** (works whenever the plugin owns the entity and its interface):

```php
 interface FooInterface
 {
-    public function setQuantity(int $quantity): void;
+    /**
+     * Passing null is a no-op (leaves the existing value untouched).
+     */
+    public function setQuantity(?int $quantity): void;
 }

 class Foo implements FooInterface
 {
     protected int $quantity = 1;

-    public function setQuantity(int $quantity): void
+    public function setQuantity(?int $quantity): void
     {
+        if (null === $quantity) {
+            return;
+        }
         $this->quantity = $quantity;
     }
 }
```

Why this beats `empty_data`: the model is the single source of truth for its defaults (`quantity = 1`, `discount = '0.0'`); no duplication between the property initializer and the form configuration; consumers extending the entity don't have to remember a form-level workaround. Document the signature change in `UPGRADE.md` — consumers implementing the interface directly need to widen their signatures to match.

**Fallback — `empty_data` on the form field** (use when you can't change the entity's interface, e.g. it's defined upstream in Sylius or another plugin):

```php
 $builder->add('quantity', IntegerType::class, [
     'label' => 'your_plugin.form.quantity',
+    'empty_data' => '1',
 ])->add('discount', NumberType::class, [
+    'empty_data' => '0.0',
 ]);
```

`empty_data` for `IntegerType` / `NumberType` is the _raw form string_ — Symfony coerces it through the data transformer. Side effect: it pre-fills the new row's inputs with the defaults (`"1"`, `"0.0000000"`), a nicer visual hint at the cost of mild surprise when the user clicks Save without typing (a "default" row is silently created). The nullable-setter approach leaves the new row's inputs empty, nudging the user to enter meaningful values.

**3. Render with the auto-provided `button_add` / `button_delete` widgets** instead of `form_row(form.foos)`. Mirror `@SyliusAdmin/product/form/sections/media/{images,add_image}.html.twig`:

```twig
{% set foos = hookable_metadata.context.form.foos %}

<div class="row">
    {% for entry in foos %}
        <div class="col-12 row mb-4 align-items-end">
            <div class="col">{{ form_row(entry.quantity) }}</div>
            <div class="col-auto mb-3">
                {{ form_widget(entry.vars.button_delete, {
                    label: 'sylius.ui.delete'|trans,
                    attr: { class: 'btn btn-outline-danger' },
                }) }}
            </div>
        </div>
    {% endfor %}
</div>

<div class="d-grid gap-2">
    {{ form_widget(foos.vars.button_add, {
        label: 'your_plugin.ui.add_foo'|trans,
        attr: { class: 'btn btn-outline-primary' },
    }) }}
</div>
```

`LiveCollectionType::buildView()` decorates `button_add` with `data-action="live#action"` + `data-live-action-param="addCollectionItem"`, and `finishView()` decorates each entry's `button_delete` with `removeCollectionItem`. No client-side JS required.

### Pitfall: per-entry fields that depend on parent context

A subtle trap: any per-entry form field that depends on context from the **parent entity** (e.g. "the product owning this price tier") cannot be added via a `PRE_SET_DATA` listener that reads the new entry's own backreference. Reason: when the user clicks **Add**, Live Components creates a fresh entry on the server (`new PriceTier()`) and builds the form for it **before** the parent's `addPriceTier()` assigns the backreference. The listener fires while `$entry->getParent()` is still `null`, so the field is silently omitted on freshly-added rows even though it appears on existing ones. No error — the UI just renders inconsistent rows.

**Fix: forward the parent through `entry_options`.** This mirrors Sylius core's product-image setup (`Sylius\Bundle\CoreBundle\Form\Extension\ProductTypeExtension` passes `'entry_options' => ['product' => $options['data']]` down to `ProductImageType`).

```php
// 1) The outer form extension passes the parent entity down.
public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $product = $options['data'] ?? null;

    $builder->add('priceTiers', PriceTierCollectionType::class, [
        'label' => false,
        'constraints' => [new Valid()],
        'entry_options' => [
            'product' => $product instanceof ProductInterface ? $product : null,
        ],
    ]);
}

// 2) The collection type wrapper preserves its own defaults via a normalizer.
$resolver
    ->setDefaults(['entry_options' => []])
    ->setNormalizer('entry_options', static fn (Options $o, mixed $v) =>
        array_merge(['label' => false], is_array($v) ? $v : []));

// 3) The entry type declares the `product` option and uses it unconditionally.
public function configureOptions(OptionsResolver $resolver): void
{
    parent::configureOptions($resolver);
    $resolver
        ->setDefault('product', null)
        ->setAllowedTypes('product', [ProductInterface::class, 'null']);
}

public function buildForm(FormBuilderInterface $builder, array $options): void
{
    // …other fields…
    if ($options['product'] instanceof ProductInterface) {
        $builder->add('productVariant', ProductVariantChoiceType::class, [
            'product' => $options['product'],
            'required' => false,
        ]);
    }
}
```

**Do not** use a `PRE_SET_DATA` listener that reads `$entity->getParent()` for this — it only works on entries loaded from the DB; freshly-added rows fail silently.

### Constraint: the parent form must itself be a Live Component

The Live actions only dispatch if the surrounding form is a Live Component. Sylius admin's product form is — see `vendor/sylius/sylius/src/Sylius/Bundle/AdminBundle/Resources/config/app/twig_hooks/product/update.yaml`:

```yaml
'sylius_admin.product.update.content':
    form:
        component: 'sylius_admin:product:form'
```

Plugins hooking into the product form get add/remove for free. Plugins hooking into a **non-**Live-Component form must either expose their own component or add and remove rows with plain JavaScript. Ship the script in the plugin's `public/` directory and load it from a template hooked into the form's page, as the skeleton's `CLAUDE.md` describes under "JavaScript". A Stimulus controller would need every application to import and build the plugin's assets. Don't reach for `LiveCollectionType` somewhere it can't work.

---

## 8. Doctrine: prefer `ManagerRegistry` + lazy resolution

Sylius 2.x and modern Doctrine prefer pulling managers via `ManagerRegistry` rather than injecting `EntityManagerInterface` directly. This avoids binding services to a single hard-coded manager and plays well with multi-manager setups.

```php
use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;

final class YourService
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var class-string $class */
        private readonly string $class,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function doWork(): void
    {
        $manager = $this->getManager($this->class);
        // …
    }
}
```

Wire with `service('doctrine')` and the model class-string parameter (`%your_plugin.model.foo.class%`).

CLAUDE.md guidance:

```
- For services that need a Doctrine `EntityManager`, don't inject
  `EntityManagerInterface` (or `your_plugin.manager.foo`) directly. Inject
  `Doctrine\Persistence\ManagerRegistry` plus the relevant `class-string`
  (e.g. `%your_plugin.model.foo.class%`) and `use Setono\Doctrine\ORMTrait;`
  so the manager is resolved lazily via `$this->getManager($class)`.
```

For batch operations on large tables, switch to [`ocramius/doctrine-batch-utils`](https://github.com/Ocramius/DoctrineBatchUtils) so prunes/migrations stay memory-safe.

---

## 9. Test application

Update `tests/Application/` **in place**, file-by-file, using [`Setono/SyliusPluginSkeleton`](https://github.com/Setono/SyliusPluginSkeleton) as the reference. **Do not** wipe the directory and copy the skeleton over the top — you'll lose plugin-specific test wiring (a test-app `Product` entity that mixes in `ProductTrait`, custom resource overrides, fixtures, route files that reference plugin routes, etc.). Diff each file against the skeleton and pull in the changes.

Key files to align with the skeleton:

* `tests/Application/Kernel.php` — extends Sylius's standard test kernel.
* `tests/Application/config/bundles.php` — every Sylius 2.x bundle plus your plugin. **Add `Sylius\TwigHooks\SyliusTwigHooksBundle`** if you use Twig hooks (the skeleton has it pre-registered).
* `tests/Application/config/packages/*.yaml` — package configuration.
* `tests/Application/public/index.php` — see §11b on the `HEADER_X_FORWARDED_ALL` removal.

**Bundle order.** The skeleton registers the plugin last in `bundles.php`. That works as long as the plugin's prepended config doesn't reference the plugin's own `%parameters%` (§5). A plugin that still prepends a `sylius_grid` array grid with `%your_plugin.model.thing.class%` must be registered before `SyliusGridBundle`, or the container fails with "You have requested a non-existent parameter". Converting the grid to a PHP grid class (§5) removes that requirement. If some other prepended config has to reference a plugin parameter, register the plugin before the bundle that receives it, here and in the README's installation steps.

Other key points:

* Functional tests **require MySQL** — make this explicit in CLAUDE.md and CI.

* API tests need nothing beyond the skeleton's `require-dev` (§1): it has `api-platform/symfony` and `api-platform/doctrine-orm`, and `sylius/sylius` brings `lexik/jwt-authentication-bundle`.

* `dama/doctrine-test-bundle: ^8.6` is **optional** — it runs each test in a transaction that is rolled back; the skeleton doesn't require it. Add it once tests write to the database (browser tests usually do, §16), not preemptively. If you add it, register `DAMA\DoctrineTestBundle\DAMADoctrineTestBundle::class => ['test' => true]` in `tests/Application/config/bundles.php` and the PHPUnit extension in `phpunit.xml.dist`:

    ```xml
    <extensions>
        <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    </extensions>
    ```

    DAMA also needs savepoints for the transactions nested in each test's transaction. Sylius 2.2 requires DBAL 3, where they are opt-in, and without them the test app's container doesn't compile ("This bundle relies on savepoints for nested database transactions"). Keep `use_savepoints: true` under `doctrine.dbal` in `tests/Application/config/packages/doctrine.yaml`, which the skeleton sets already:

    ```yaml
    doctrine:
        dbal:
            use_savepoints: true
    ```

* The skeleton splits `phpunit.xml.dist` into `unit` and `functional` test suites so unit tests stay fast and CLI-friendly without booting MySQL. Mirror that split: keep kernel-free logic under `tests/Unit/` and anything that boots the app (and therefore needs MySQL) under `tests/Functional/`:

    ```xml
    <phpunit bootstrap="tests/Application/config/bootstrap.php" ...>
        <testsuites>
            <testsuite name="unit">
                <directory>tests/Unit</directory>
            </testsuite>
            <testsuite name="functional">
                <directory>tests/Functional</directory>
            </testsuite>
        </testsuites>
    </phpunit>
    ```

* A plugin with UI surfaces adds a third suite, `browser` (`tests/Browser/`, playwright-symfony), with its first UI change (§16). The skeleton ships without it; its `CLAUDE.md` ("Add when needed" → "Browser tests") governs the setup, including the CI job.

* The CI jobs pick their suites. The mutation-tests job has no MySQL, assets or browsers, so the skeleton's `infection.json5` limits Infection's initial run to `unit` (`"testFrameworkOptions": "--testsuite=unit"`). Infection runs without `--with-uncovered`, so code that only functional tests reach (repositories, Doctrine listeners) gets no mutants, and `minMsi` and `minCoveredMsi` both stay at 100. Code coverage counts `unit` and `functional`; once a `browser` suite exists, pass `testsuite: "unit,functional"` to the `code-coverage` job, which installs no browsers.

Booting the test app locally (document this verbatim in CLAUDE.md):

```bash
cd tests/Application
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:create
php bin/console sylius:fixtures:load default --no-interaction  # admin login: sylius / sylius
php bin/console assets:install public
yarn install && yarn build
symfony server:start                                            # https://127.0.0.1:8000/admin
```

---

## 10. Tooling: PHPStan, ECS, Rector, dependency analysis

Standardize on the `setono/sylius-plugin: ^2.0` toolchain and CI composite actions. As a single dev-dep it replaces the old `setono/code-quality-pack` and pulls in:

* PHPStan + extensions (`phpstan-symfony`, `phpstan-doctrine`, `phpstan-phpunit`, `phpstan-strict-rules`, `jangregor/phpstan-prophecy`)
* ECS rules (`sylius-labs/coding-standard`)
* Rector sets
* `shipmonk/composer-dependency-analyser`
* GitHub composite actions (`setono/sylius-plugin/<job>@v2`)

### Files renamed / added during the upgrade

If you're upgrading an existing 1.x plugin, expect to **delete**:

* `composer-require-checker.json`
* `composer-unused.php`
* `psalm.xml`
* `infection.json.dist`
* Hand-rolled `.github/workflows/*.yaml`
* Standalone `.github/workflows/backwards-compatibility-check.yaml` — folds into `build.yaml`

And to **add**:

* `composer-dependency-analyser.php` — replaces require-checker + composer-unused
* `phpstan.neon` — replaces psalm
* `infection.json5` — replaces `infection.json.dist`
* `.github/workflows/build.yaml` — single workflow using the composite actions

`phpstan.neon`:

```yaml
parameters:
    level: max
    paths: [src, tests]
    excludePaths: [tests/Application/*]
    bootstrapFiles: [tests/Application/config/bootstrap.php]
    doctrine:
        repositoryClass: Doctrine\ORM\EntityRepository
    reportUnmatchedIgnoredErrors: false
    treatPhpDocTypesAsCertain: false
```

* **No `symfony.consoleApplicationLoader` and no `doctrine.objectManagerLoader`.** Both boot the test application, which the static-code-analysis job can't do: it removes `sylius/sylius` before installing, so the test app's bundles are missing. phpstan-symfony only calls the console loader when it analyses a `Command` subclass, so nothing fails until the plugin adds its first console command, and then only in CI, with `Internal error: Class "Symfony\Bundle\MonologBundle\MonologBundle" not found`. Without the loader, `InputInterface::getOption()` and `getArgument()` return `mixed`, and level max makes the command narrow them (e.g. with `Assert::string()`). If the plugin has a `tests/PHPStan/console_application.php` from an earlier version of this playbook, delete it together with the loader.
* **No `ignoreErrors` and no baseline.** Fix `missingType.generics` and `missingType.iterableValue` errors by declaring the generic and iterable types. When an ignore is unavoidable, scope it to the error identifier and the line, with a comment saying why: `// @phpstan-ignore missingType.generics (reason)`.

`ecs.php`:

```php
return static function (ECSConfig $config): void {
    $config->import('vendor/sylius-labs/coding-standard/ecs.php');
    $config->paths([
        'src',
        'tests',
        'composer-dependency-analyser.php',
        'rector.php',
    ]);
    $config->skip([
        // Generated (and gitignored): Symfony 7.4 writes it whenever it builds the debug container
        'tests/Application/config/reference.php',
        'tests/Application/node_modules/**',
        'tests/Application/var/**',
    ]);
};
```

Unlike PHPStan and Rector, ECS checks the test application's own PHP files (`Kernel.php`, `config/bootstrap.php`, …). Skip only what's generated or installed there, not all of `tests/Application/`.

`rector.php` (target the same PHP floor you committed to):

```php
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->cacheDirectory('./.build/rector');
    $rectorConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);
    $rectorConfig->skip([__DIR__ . '/tests/Application']);
    $rectorConfig->sets([LevelSetList::UP_TO_PHP_82]);
};
```

`composer-dependency-analyser.php` (catches missing/unused composer deps):

```php
return (new Configuration())->addPathToExclude(__DIR__ . '/tests');
```

CI (`.github/workflows/build.yaml`) — use the composite actions, matrix over PHP and Symfony:

```yaml
on:
    push:
        branches: ["[0-9]+.x", "[0-9]+.[0-9]+.x"]
    pull_request: ~
    workflow_dispatch: ~

jobs:
    backwards-compatibility:
        runs-on: ubuntu-latest
        if: github.event_name == 'pull_request'
        steps: [{ uses: setono/sylius-plugin/backwards-compatibility@v2 }]

    coding-standards:
        runs-on: ubuntu-latest
        steps: [{ uses: setono/sylius-plugin/coding-standards@v2 }]

    dependency-analysis:
        strategy:
            matrix:
                php-version: ["8.2", "8.3", "8.4", "8.5"]
                dependencies: ["lowest", "highest"]
                symfony: ["~6.4.0", "~7.4.0"]
        steps:
            - uses: setono/sylius-plugin/dependency-analysis@v2
              with:
                  php-version: ${{ matrix.php-version }}
                  dependencies: ${{ matrix.dependencies }}
                  symfony: ${{ matrix.symfony }}

    static-code-analysis: { /* same matrix */ }
    unit-tests:           { /* same matrix */ }
    functional-tests:     { /* same matrix, plus node-version: "24" */ }
    # …mutation-tests, code-coverage (also node-version: "24")
```

* **Triggers:** limit `push` to version branches. `pull_request` already runs on every push to a pull request branch, so an unfiltered `push: ~` runs every job twice. Don't shorten the patterns to `"*.x"`: `*` also matches topic branches such as `fix-foo-2.2.x`. The patterns start with `[`, so YAML needs them quoted.
* **Node:** pass `node-version: "24"` to `functional-tests` and `code-coverage`, the two actions that build the test application's assets. Their default is Node 20, which is end of life, and `@sylius-ui/admin` 2.3 refuses to install on it (it requires `^22.13.0 || >=24.0`).

`composer.json` scripts:

```json
"scripts": {
    "analyse": "phpstan analyse",
    "check-style": "ecs check",
    "fix-style": "ecs check --fix",
    "phpunit": "phpunit"
}
```

`composer phpunit` runs every suite in `phpunit.xml.dist`. Run a single suite with `vendor/bin/phpunit --testsuite=unit` (or `functional`), as the skeleton's `CLAUDE.md` documents, rather than adding a script per suite.

Code-quality section of CLAUDE.md: take the **Quality Gates** section from the skeleton's `CLAUDE.md` rather than writing your own, so the plugin's rules match what its CI enforces. In particular, PHPStan runs without a baseline or global ignores, and not every tool skips `tests/Application/`: ECS checks the test app's PHP files.

`.gitattributes`: mirror the skeleton's. It export-ignores every root-level dev file, so the dist archive ships only what the plugin needs at runtime. Without the update, the plugin's archive ships the new dev files.

* **Add** `export-ignore` lines for the new dev files: `/composer-dependency-analyser.php`, `/phpstan.neon`, `/infection.json5`, and `/CLAUDE.md` and `/.symfony-lsp.json` once the plugin has them.
* **Drop** the lines for the files you deleted, such as `/composer-require-checker.json`, `/composer-unused.php`, `/psalm.xml`, `/psalm-baseline.xml` and `/infection.json.dist`. Stale lines do no harm, but they hide which dev files the plugin really has.
* **Check** a path with `git check-attr export-ignore -- <path>`, which reads the working tree's `.gitattributes`. `git archive HEAD | tar -t` lists what the last commit ships.

---

## 11. Required `declare(strict_types=1);`

Add `declare(strict_types=1);` to every PHP file in `src/` and `tests/` (Rector + ECS will enforce). Document in CLAUDE.md as a hard requirement.

---

## 11a. Doctrine ORM 3 drops PHPDoc annotations

Sylius 2.x pulls in Doctrine ORM 3, which **silently ignores** PHPDoc-style mappings (`@ORM\OneToMany(...)` in a docblock). Inverse-side associations stop wiring; schema validation reports `The association ... refers to the inverse side field ... which does not exist`.

Convert every PHPDoc mapping in your entities, traits, and mapped superclasses to PHP 8 attributes:

```php
-/**
- * @ORM\OneToMany(targetEntity=Bar::class, mappedBy="foo")
- */
-private Collection $bars;
+#[ORM\OneToMany(targetEntity: Bar::class, mappedBy: 'foo')]
+private Collection $bars;
```

Run `bin/console doctrine:schema:validate` after the conversion. Pay particular attention to traits the plugin ships — consumers mixing them into their own entities will inherit the broken mapping otherwise.

---

## 11b. Deprecation sweeps: Symfony 7, PHP 8.4 and PHP 8.5

The new CI matrix (PHP 8.2/8.3/8.4/8.5 × Symfony 6.4/7.4) catches things older toolchains hid. Sweep proactively:

* **`Request::HEADER_X_FORWARDED_ALL` removed in Symfony 6.0** — so it's absent on every supported version (`6.4` and `7.4`), and `HEADER_X_FORWARDED_ALL ^ HEADER_X_FORWARDED_HOST` fatals at runtime once `TRUSTED_PROXIES` is set. Replace it in `tests/Application/public/index.php` with the explicit bitmask `HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_PORT | HEADER_X_FORWARDED_PROTO` (the skeleton already does this — mirror it).
* **PHP 8.4 deprecates implicit nullable parameters.** `function foo(Foo $x = null)` now warns; rewrite to `function foo(?Foo $x = null)`. Rector can do this for you — run the dry-run before committing.
* **PHP 8.5 deprecates more legacy syntax and calls that no longer do anything.** The ones plugins are likely to hit:
  * `setAccessible()` on `ReflectionProperty`, `ReflectionMethod` and the other Reflection classes, common in tests. It has had no effect since PHP 8.1, so delete the calls.
  * The non-canonical casts `(boolean)`, `(integer)`, `(double)` and `(binary)`. Use `(bool)`, `(int)`, `(float)` and `(string)`.
  * `null` as an array offset or as the key passed to `array_key_exists()`. Use `''`.
  * The backtick operator. Call `shell_exec()`, or better, use `symfony/process`.
  * `SplObjectStorage::attach()`, `detach()` and `contains()`. Use `offsetSet()`, `offsetUnset()` and `offsetExists()`.
  * `curl_close()`, `finfo_close()`, `imagedestroy()` and `xml_parser_free()`. The handles are freed automatically, so delete the calls.
  * The `PDO::MYSQL_ATTR_*` constants, replaced by `Pdo\Mysql::ATTR_*`. `Pdo\Mysql` only exists from PHP 8.4, so with an 8.2 floor pick the constant by `PHP_VERSION_ID`.
  * `__sleep()` and `__wakeup()` are only soft-deprecated (no notice yet). Use `__serialize()` and `__unserialize()` in new code.
* Run `composer analyse` and `composer phpunit` against each matrix cell locally if you have the PHP versions installed, otherwise rely on CI.

---

## 12. Mocks: standardize on Prophecy

The `setono/sylius-plugin` test stack uses Prophecy. Don't mix with PHPUnit's native `createMock()` / `createStub()` — keep doubles consistent across the suite.

```php
use Prophecy\PhpUnit\ProphecyTrait;

final class FooTest extends TestCase
{
    use ProphecyTrait;

    public function testIt(): void
    {
        $bar = $this->prophesize(BarInterface::class);
        $bar->doIt('x')->willReturn('y');

        $foo = new Foo($bar->reveal());
    }
}
```

Document in CLAUDE.md so future contributors don't drift.

---

## 13. Translations: key hygiene and target locales

**Key hygiene.** Update **every** locale file in `translations/`, not just English. Missing translations leak the raw key into the UI for non-English admins.

CLAUDE.md guidance:

```
- When adding or updating translation keys, update every locale file in
  `translations/` (e.g. `messages.en.yaml`, `messages.da.yaml`, ...), not just
  English. Missing translations leak the raw key into the UI for non-English
  admins.
```

If you remove translation keys, list them in `UPGRADE.md` so consumers cleaning up their own translation overrides know what to drop.

**Target locales.** Use the upgrade as the moment to bring the plugin's translation coverage up to the Setono standard. The source locale is English (`en`); translate every domain in `translations/` into the following locales:

* **Nordic**: Danish (`da`), Swedish (`sv`), Norwegian (`no`), Finnish (`fi`)
* **Large EU**: German (`de`), French (`fr`), Spanish (`es`), Italian (`it`), Dutch (`nl`), Polish (`pl`)
* **Other common Sylius locales**: Portuguese (`pt`), Czech (`cs`), Hungarian (`hu`), Romanian (`ro`), Ukrainian (`uk`)

Translation files follow Symfony's `<domain>.<locale>.<format>` naming (e.g. `messages.da.yaml`, `validators.de.yaml`).

CLAUDE.md guidance:

```
- Plugins should ship translations for these locales (source locale is English):
  Nordic — da, sv, no, fi; Large EU — de, fr, es, it, nl, pl; Other common
  Sylius locales — pt, cs, hu, ro, uk. Translation files live in `translations/`
  and follow Symfony's `<domain>.<locale>.<format>` naming.
```

---

## 14. Validators: extend `ConstraintValidatorTestCase`

Sylius 2.x leans heavily on Symfony's validator. Where you used to roll your own validator base test class, switch to `Symfony\Component\Validator\Test\ConstraintValidatorTestCase`:

```php
final class FooValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new FooValidator(/* … */);
    }

    public function testItRejectsX(): void
    {
        $this->validator->validate($value, new Foo());
        $this->buildViolation('your_plugin.foo.message')
            ->atPath('property.path')
            ->setParameter('{{ x }}', 'y')
            ->assertRaised();
    }
}
```

CLAUDE.md guidance:

```
- **Constraint validators**: extend
  `Symfony\Component\Validator\Test\ConstraintValidatorTestCase`. Implement
  `createValidator(): ConstraintValidatorInterface`, then assert with
  `$this->buildViolation(...)->atPath(...)->setParameter(...)->assertRaised()`
  and `$this->assertNoViolation()`.
```

---

## 15. Forms and console commands: stay in the unit suite when possible

* **Form types**: extend `Symfony\Component\Form\Test\TypeTestCase`, stub external form types (e.g. `ChannelChoiceType`) via `PreloadedExtension`, assert against synchronized model and view data after `submit()`. Reference: [Symfony 6.4 form unit testing guide](https://symfony.com/doc/6.4/form/unit_testing.html).
* **Console commands**: use `Symfony\Component\Console\Tester\CommandTester` against a stand-alone `Application` (no kernel) with mocked collaborators; assert on exit code and `getDisplay()`. Reference: [Symfony 6.4 command testing guide](https://symfony.com/doc/6.4/console.html#testing-commands).

Both stay in the unit suite — no kernel boot, no MySQL.

---

## 16. UI verification with browser tests

Cover every UI change (admin form, grid, page) with a browser test in `tests/Browser/`, and look at a screenshot of each page it changes before reporting the work as done. The unit and functional suites, PHPStan and ECS aren't enough — Twig hook wiring fails silently and only shows in the rendered page.

Browser tests use [playwright-php/playwright-symfony](https://github.com/playwright-php/playwright-symfony) in a third PHPUnit suite, `browser` (§9). Add the suite with the plugin's first UI change. The skeleton's `CLAUDE.md` ("Add when needed" → "Browser tests") governs the setup: installing the bundle and Chromium, `/bundles` assets, and the CI job. Why browser tests rather than checking by hand or through the Playwright MCP:

* **They stay.** CI runs them on every build, so the check keeps holding after the change. A manual or MCP check happens once and leaves nothing behind.
* **They're always there.** playwright-symfony is a dev dependency of the plugin, so every contributor and agent has it. The MCP exists only where a developer installed it.
* **They see the test's data.** playwright-symfony handles the browser's requests in the test's own kernel, inside the DAMA transaction (§9), so a test sets up exactly the case it checks.
* **They give screenshots too.** `$page->screenshot()` shows what the page looks like. Pass it an absolute path, because the Playwright server resolves a relative one against its own working directory.

CLAUDE.md guidance:

```
- When you build or change a feature with a UI surface (admin form, grid, page),
  cover it with a browser test in `tests/Browser/`, and look at a screenshot of
  each page it changes (`$page->screenshot()`, absolute path) before reporting the
  task as complete. Don't rely on the unit and functional suites, PHPStan or ECS
  for UI work: Twig hook wiring fails silently and only the rendered page shows it.
```

When you also run the test app server by hand, these tips save real time:

```
- Before running `symfony server:start`, run `symfony server:status` first — the
  test app server may already be up from a previous step. Starting a second
  instance fails noisily.
- `symfony server:status` is **per-project**: it inspects the cwd to figure out
  which app to ask about. Always run `cd tests/Application && symfony
  server:status` before deciding whether to start a new server.
- `symfony server:start --dir=...` interprets the dir relative to its own
  internals and rejects relative paths; pass `$(pwd)/tests/Application` if you
  really need to start a fresh server.
```

---

## 17. Behavior changes: flip defaults deliberately and document them

Don't smuggle behavior changes into a major bump. For each default-value flip:

1. Decide the new default consciously (which one matches the most common usage?).
2. Document the change in `UPGRADE.md` with the **old → new** value and what to set explicitly to keep the old behavior.
3. Update `README.md` to describe the **current** behavior — it's what end users read first; it must reflect what the latest published version does, not what an old version did.

`UPGRADE.md` covers the migration path; `README.md` covers the current product. Keep them in sync but distinct.

CLAUDE.md guidance:

```
- When you make a breaking change a plugin user would need to act on during a
  major-version upgrade (default-value flips, renamed/removed public classes or
  services, template-path moves, changed model APIs, removed config keys),
  document it in `UPGRADE.md` under the relevant "Upgrading from X to Y"
  section. Skip changes that consumers don't have to react to: dev tooling,
  listener-priority tweaks, translation-key additions, and other internal
  refactors.
- When you ship a change that affects what the plugin does for end users — new
  feature surfaces, new configuration keys, behavior shifts, removed UI —
  update `README.md` to describe the current state.
```

---

## 18. `UPGRADE.md` shape

Build it incrementally as you remove/rename things. Recommended sections under `## Upgrading from X to Y`:

1. **Requirements** — PHP/Symfony/Sylius floors.
2. **Default value changes** on entities/models.
3. **Plugin file layout** — `Resources/` → repo root, with mapping table.
4. **Template paths** — pointer to Twig hooks.
5. **Renamed/moved commands** — old class + command name → new class + command name table.
6. **Removed handlers / services / events** — what they were and what replaces them (or "none — opt-in is now config-driven").
7. **Removed classes, services, templates, translation keys** — single big table with `Removed | Replacement` columns. This is the most-read part of the guide.
8. **Scope changes** — e.g. "now admin-only".

For the final section, write entries like these:

> | `App\Handler\SomeCommandHandler` (and `Interface`, `Command`) | Inlined into `App\EventListener\SomeListener` |
> | `App\Form\Extension\SomeTypeExtension` | (none — opt-in is config-driven) |
> | Snake-cased service ids `your_plugin.command.prune`, `your_plugin.factory.foo`, … | All renamed to the FQCN of their concrete class. The corresponding `*Interface` services keep working as aliases pointing at the new FQCN ids. The Sylius-resource-bundle-managed ids (`your_plugin.factory.foo`, `repository.foo`, `manager.foo`) are unchanged because they are auto-generated from the resource config. |

---

## 19. Pre-commit checks

Codify the local run-before-you-commit ritual in CLAUDE.md so the agent doesn't push regressions CI will catch in 30 seconds:

```
- Before each commit, run the code-quality tools and fix what they flag:
  `composer fix-style` (or `composer check-style` if you only want a report),
  `composer analyse` (PHPStan at `level: max`), and `composer phpunit`. Don't
  commit on top of pre-existing failures — re-run the suite locally first so CI
  doesn't catch regressions you could've caught in seconds.
```

---

## 20. Working-in-this-repo conventions to add to CLAUDE.md

These aren't strictly part of the v1→v2 upgrade, but they tend to surface during one and are worth codifying once you have the agent's attention:

* Always use **relative paths** in shell commands. Absolute paths inside the working directory trigger a Claude Code permission prompt.
* If you `cd` into `tests/Application/` for a step, `cd` back to the project root before subsequent commands. Don't compensate by prepending an absolute path.
* Run the test-app console from the project root via `./tests/Application/bin/console <cmd>` instead of `cd tests/Application && php bin/console <cmd>`.
* Twig extension/runtime split (see §7).
* Translation key hygiene and target locales (see §13).
* Pre-commit checks (see §19).
* `ManagerRegistry` over `EntityManagerInterface` (see §8).
* FQCN service ids (see §4).
* Bundle prepending over YAML imports (see §5).

---

## 21. Suggested order of operations

If you're driving this with an agent, here's an order that minimizes rebuild loops:

1. **Bump `composer.json`** (§1) — get the dependency graph resolving on the new floor before touching code.
2. **Move files** (§2) — `Resources/` → repo root in one mechanical pass; update `getPath()` **and** `getConfigFilesPath()`.
3. **Convert DI to PHP DSL** (§3) — file-by-file. Keep service ids identical for now.
4. **Update `tests/Application/` in place** (§9) — diff against `Setono/SyliusPluginSkeleton`. Register `SyliusTwigHooksBundle` if relevant. Get `vendor/bin/phpunit --testsuite=functional` running with at least one trivial test.
5. **Update tooling configs** (§10–11) — PHPStan, ECS, Rector, CI. Delete the old quality-pack files; add the new ones. Get them green.
6. **Convert Doctrine PHPDoc mappings to attributes** (§11a) — run `doctrine:schema:validate` to confirm.
7. **Sweep Symfony 7 / PHP 8.4 / PHP 8.5 deprecations** (§11b).
8. **Move bundle config to `prepend()`** (§5).
9. **Rework routing** (§6).
10. **Port templates to Twig hooks** (§7) — keep working `AbstractTypeExtension` form extensions and `sylius.menu.admin.main` listeners; only port template overrides and page menu builders. Cover each surface with a browser test (§16).
11. **Migrate admin `CollectionType` fields to `LiveCollectionType`** (§7a) — needed for any plugin that ships an inline collection in the admin product/variant form.
12. **Switch service ids to FQCN** (§4) — touch only services you're already modifying. Catch known Sylius core renames (`sylius.integer_distributor` → `sylius.distributor.integer`).
13. **Migrate Doctrine call sites to `ManagerRegistry`** (§8).
14. **Pin behavior changes intentionally** (§17), update `UPGRADE.md` (§18) and `README.md` continuously.
15. **Translate into all target locales** (§13) — add or refresh `translations/<domain>.<locale>.yaml` for every locale in the target set, not just English.
16. **Final pass**: `composer fix-style && composer analyse && composer phpunit` clean (`phpunit` runs the `browser` suite too), a browser test for every UI surface (§16), CI green on the full matrix.

---

## 22. What NOT to do

* Don't bulk-rename old snake-cased service ids that the upgrade doesn't already touch — it's noise that bloats `UPGRADE.md` and irritates consumers.
* Don't add backwards-compatibility shims (re-exports, deprecated aliases for renamed classes) for a major version. Document and remove cleanly. The whole point of a major bump is permission to break.
* Don't keep the old `Resources/` paths "for compatibility" — Sylius 2.x's mapping discovery and bundle-relative lookups assume the new layout.
* Don't keep YAML config files that `prepend()` could ship inline (§5).
* Don't ship XML service configuration — convert to PHP DSL (§3).
* **Don't wipe and replace `tests/Application/`.** You'll lose the plugin-specific test wiring (custom entities mixing in plugin traits, fixtures, route references). Diff in place against the skeleton (§9).
* **Don't rewrite working `AbstractTypeExtension` form extensions as Twig hook subscribers.** Sylius 2.x core still uses form extensions. Do give each field they add a hookable that renders it, though: admin and shop forms don't render leftover fields, so without one the field never reaches the page (§7).
* Don't migrate an admin `CollectionType` to v2 without switching `getParent()` to `LiveCollectionType` — the Add/Delete buttons silently break (§7a).
* Don't leave Doctrine PHPDoc mappings (`@ORM\OneToMany`) in entities or traits — ORM 3 ignores them silently and inverse-side associations stop wiring (§11a).
* Don't claim a UI change works because the unit and functional suites pass. Twig hook misconfiguration only surfaces in a real browser, so cover it with a browser test (§16).
* Don't ship the major release with unresolved PHPStan or ECS errors — the matrix CI will catch them on a PHP/Symfony combination you didn't test locally.
