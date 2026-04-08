# Serverless OGM Bundle

Symfony integration for the [Serverless OGM](https://github.com/likeuntomurphy/serverless-ogm). Registers the `DocumentManager`, discovers document classes automatically, and provides a DynamoDB profiler panel for the Symfony toolbar.

## Installation

```bash
composer require likeuntomurphy/serverless-ogm-bundle
```

## Configuration

```yaml
# config/packages/serverless_ogm.yaml
serverless_ogm: ~
```

The defaults connect to DynamoDB using the standard AWS credential chain (IAM role on Lambda, environment variables locally). Override for local development:

```yaml
# config/packages/serverless_ogm.yaml
when@dev:
    serverless_ogm:
        dynamodb:
            endpoint: '%env(DYNAMODB_ENDPOINT)%'
            credentials:
                key: 'local'
                secret: 'local'
```

### Full configuration reference

```yaml
serverless_ogm:
    dynamodb:
        region: 'us-east-1'     # AWS region (default: us-east-1)
        endpoint: ~              # Override for DynamoDB Local
        credentials:
            key: ~               # Explicit access key (default: null, uses credential chain)
            secret: ~            # Explicit secret key
    table_suffix: ''             # Appended to all table names (e.g. '_test')
```

## Document discovery

The bundle uses Symfony 7.3+ [resource tags](https://symfony.com/blog/new-in-symfony-7-3-dependency-injection-improvements) to discover document classes. Any class with the `#[Document]` attribute is automatically registered with the `MetadataFactory` and excluded from the service container — no directory scanning, no explicit class lists.

```php
use Likeuntomurphy\Serverless\OGM\Mapping\Document;
use Likeuntomurphy\Serverless\OGM\Mapping\Field;
use Likeuntomurphy\Serverless\OGM\Mapping\PartitionKey;

#[Document(table: 'users', pk: 'PK')]
class User
{
    #[PartitionKey]
    public string $email;

    #[Field]
    public string $name;
}
```

No further configuration needed. The class is discovered, mapped, and excluded from the container automatically.

## Services

The bundle registers the following services, all autowirable:

| Service | Description |
|---|---|
| `DocumentManager` | The main persistence interface. Tagged with `kernel.reset` to clear the identity map between requests. |
| `MetadataFactory` | Holds metadata for all registered document classes. |
| `DynamoDbClient` | The AWS SDK client, configured from the bundle config. |

## Table creation

Create DynamoDB tables for all mapped documents:

```bash
php bin/console table:create
```

The command reads partition and sort key types from PHP property types (`string` maps to `S`, `int`/`float` to `N`). Tables are created with on-demand billing (`PAY_PER_REQUEST`).

## Profiler

When the Symfony profiler is installed (`symfony/web-profiler-bundle`), the bundle adds a DynamoDB panel to the toolbar showing:

- **Operations** — every DynamoDB API call with operation name, table, and execution time
- **Identity map** — hit/miss counts showing how many `find()` calls were served from cache
- **Hydrations** — how many entities were hydrated from DynamoDB responses

The profiler hooks into the AWS SDK middleware stack and the OGM's `ProfilingLogger` interface. It is registered only when the profiler service is available — zero overhead in production.

## Requirements

- PHP >= 8.5
- `likeuntomurphy/serverless-ogm` ^0.1
- `symfony/framework-bundle` ^8.0

## License

MIT
