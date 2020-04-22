# Setono Sylius Plugin Skeleton

[![Latest Version][ico-version]][link-packagist]
[![Latest Unstable Version][ico-unstable-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Quality Score][ico-code-quality]][link-code-quality]

[Setono](https://setono.com) have made a bunch of [plugins for Sylius](https://github.com/Setono), and we have some guidelines
which we try to follow when developing plugins. These guidelines are used in this repository and it gives you a very
solid base when developing plugins.

Enjoy! 

## Quickstart

1. Run `composer create-project setono/sylius-plugin-skeleton ProjectName`.

2. From the plugin skeleton root directory, run the following commands:

    ```bash
    $ php init
    $ (cd tests/Application && yarn install)
    $ (cd tests/Application && yarn build)
    $ (cd tests/Application && bin/console assets:install)
    
    $ (cd tests/Application && bin/console doctrine:database:create)
    $ (cd tests/Application && bin/console doctrine:schema:create)
   
    $ (cd tests/Application && bin/console sylius:fixtures:load -n)
    ```
   
3. Start your local PHP server: `symfony serve` (see https://symfony.com/doc/current/setup/symfony_server.html for docs)

To be able to setup a plugin's database, remember to configure you database credentials in `tests/Application/.env` and `tests/Application/.env.test`.

[ico-version]: https://poser.pugx.org/setono/sylius-plugin-skeleton/v/stable
[ico-unstable-version]: https://poser.pugx.org/setono/sylius-plugin-skeleton/v/unstable
[ico-license]: https://poser.pugx.org/setono/sylius-plugin-skeleton/license
[ico-github-actions]: https://github.com/Setono/SyliusPluginSkeleton/workflows/build/badge.svg
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Setono/SyliusPluginSkeleton.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/setono/sylius-plugin-skeleton
[link-github-actions]: https://github.com/Setono/SyliusPluginSkeleton/actions
[link-code-quality]: https://scrutinizer-ci.com/g/Setono/SyliusPluginSkeleton
