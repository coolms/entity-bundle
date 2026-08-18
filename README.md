# coolms/entity-bundle

[![CI](https://github.com/coolms/entity-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/coolms/entity-bundle/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/coolms/entity-bundle)](https://packagist.org/packages/coolms/entity-bundle)
[![PHP](https://img.shields.io/badge/php-%E2%89%A5%208.5-777bb4)](https://www.php.net/releases/8.5/en.php)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Symfony integration for [`coolms/entity`](https://packagist.org/packages/coolms/entity).

Register in `config/bundles.php`:

```php
CoolMS\EntityBundle\EntityBundle::class => ['all' => true],
```

- `DependencyInjection\Extension` -- wires the alias registry, the schema
  lookup, the extras infrastructure and the entity-factory locator, and declares
  the `entity.extras_validation` configuration.
- `DependencyInjection\Compiler\ExtrasInfrastructurePass` -- re-asserts that
  wiring after the application's own service scan, which loads later and would
  otherwise replace the definitions and silently drop decoration markers, scalar
  arguments and event-listener tags.
- `ApiPlatform\Extras*Factory` -- surfaces dynamic fields in API Platform
  property metadata so extras appear in the generated schema and OpenAPI
  document alongside static properties.
- `Controller\EntitySearchController` -- the cross-entity search endpoint.

## Installation

```bash
composer require coolms/entity-bundle
```
