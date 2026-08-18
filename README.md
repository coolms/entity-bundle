# coolms/entity-bundle

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
