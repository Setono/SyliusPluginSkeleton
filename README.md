# Setono Sylius Plugin Skeleton

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

[Setono](https://setono.com) have made a bunch of [plugins for Sylius](https://github.com/Setono?q=plugin&sort=stargazers), and we have some guidelines
that we try to follow when developing plugins. These guidelines are used in this repository, and it gives you a very
solid base when developing plugins.

Enjoy! 

## Quickstart

1. Run
    ```shell
    composer create-project --prefer-source --no-install --remove-vcs setono/sylius-plugin-skeleton:2.2.x-dev ProjectName
    ``` 
    or just click the `Use this template` button at the right corner of this repository.
2. Run
   ```shell
   cd ProjectName && composer install
   ```
3. From the plugin skeleton root directory, run the following commands:

    ```bash
    php init
    (cd tests/Application && yarn install)
    (cd tests/Application && yarn build)
    (cd tests/Application && bin/console assets:install)
    
    (cd tests/Application && bin/console doctrine:database:create)
    (cd tests/Application && bin/console doctrine:schema:create)
   
    (cd tests/Application && bin/console sylius:fixtures:load -n)
    ```
   
4. Start your local PHP server: `symfony serve` (see https://symfony.com/doc/current/setup/symfony_server.html for docs)

To be able to set up a plugin's database, remember to configure you database credentials in `tests/Application/.env` and `tests/Application/.env.test`.

## Installation

The plugin ships no Flex recipe, so an application installs it with these steps.

1. Require the plugin:

    ```shell
    composer require setono/sylius-plugin-skeleton
    ```

2. Register the plugin in `config/bundles.php`:

    ```php
    Acme\SyliusExamplePlugin\AcmeSyliusExamplePlugin::class => ['all' => true],
    ```

3. Import the plugin's routes in `config/routes/acme_sylius_example.yaml`:

    ```yaml
    acme_sylius_example:
        resource: "@AcmeSyliusExamplePlugin/config/routes.yaml"
    ```

    If your shop doesn't use localised URLs, import `@AcmeSyliusExamplePlugin/config/routes_no_locale.yaml` instead.

4. Import the plugin's admin JavaScript into your admin entry, `assets/admin/entrypoint.js` in Sylius-Standard:

    ```js
    import '@vendor/setono/sylius-plugin-skeleton/assets/admin/entrypoint';
    ```

    `@vendor` is the alias for `vendor/` that Sylius-Standard's `webpack.config.js` defines. Without it, use the path relative to the entry, `../../vendor/setono/sylius-plugin-skeleton/assets/admin/entrypoint`.

    Your admin must load `app-admin-entry`, which Sylius-Standard has done since 2.0.1. An application started from 2.0.0 builds the import but never loads it (its entry is `assets/admin/entry.js`), so add the hooks and the two one-line templates that 2.0.1 added:

    ```yaml
    # config/packages/_sylius.yaml
    sylius_twig_hooks:
        hooks:
            'sylius_admin.base#stylesheets':
                app_styles:
                    template: 'admin/stylesheets.html.twig' # {{ encore_entry_link_tags('app-admin-entry', null, 'app.admin') }}
            'sylius_admin.base#javascripts':
                app_javascripts:
                    template: 'admin/javascripts.html.twig' # {{ encore_entry_script_tags('app-admin-entry', null, 'app.admin') }}
    ```

    Then rebuild your assets:

    ```shell
    yarn build
    ```

[ico-version]: https://poser.pugx.org/setono/sylius-plugin-skeleton/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-plugin-skeleton/license
[ico-github-actions]: https://github.com/Setono/SyliusPluginSkeleton/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/SyliusPluginSkeleton/branch/2.2.x/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2FSyliusPluginSkeleton%2F2.2.x

[link-packagist]: https://packagist.org/packages/setono/sylius-plugin-skeleton
[link-github-actions]: https://github.com/Setono/SyliusPluginSkeleton/actions
[link-code-coverage]: https://codecov.io/gh/Setono/SyliusPluginSkeleton
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/SyliusPluginSkeleton/2.2.x
