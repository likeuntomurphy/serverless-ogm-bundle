<?php

namespace Likeuntomurphy\Serverless\OGMBundle;

use Aws\DynamoDb\DynamoDbClient;
use Likeuntomurphy\Serverless\OGMBundle\Command\TableCreateCommand;
use Likeuntomurphy\Serverless\OGM\DocumentManager;
use Likeuntomurphy\Serverless\OGM\Mapping\Document;
use Likeuntomurphy\Serverless\OGM\Metadata\MetadataFactory;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class ServerlessOgmBundle extends AbstractBundle implements CompilerPassInterface
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->arrayNode('dynamodb')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('region')->defaultValue('us-east-1')->end()
            ->scalarNode('endpoint')->defaultNull()->end()
            ->arrayNode('credentials')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('key')->defaultNull()->end()
            ->scalarNode('secret')->defaultNull()->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->scalarNode('table_suffix')->defaultValue('')->end()
            ->end()
        ;
    }

    /** @param array{dynamodb: array{region: string, endpoint: ?string, credentials: array{key: ?string, secret: ?string}}, table_suffix: string} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $dynamoConfig = [
            'region' => $config['dynamodb']['region'],
            'version' => 'latest',
        ];

        if (null !== $config['dynamodb']['endpoint']) {
            $dynamoConfig['endpoint'] = $config['dynamodb']['endpoint'];
        }

        if (null !== $config['dynamodb']['credentials']['key']) {
            $dynamoConfig['credentials'] = [
                'key' => $config['dynamodb']['credentials']['key'],
                'secret' => $config['dynamodb']['credentials']['secret'],
            ];
        }

        $services->set(DynamoDbClient::class)
            ->args([$dynamoConfig])
        ;

        $services->set(MetadataFactory::class)
            ->public()
        ;

        $services->set(DocumentManager::class)
            ->args([
                service(DynamoDbClient::class),
                service(MetadataFactory::class),
                $config['table_suffix'],
                service('event_dispatcher'),
            ])
            ->tag('kernel.reset', ['method' => 'clear'])
        ;

        $services->set(TableCreateCommand::class)
            ->args([
                service(DocumentManager::class),
            ])
            ->autoconfigure()
        ;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(
            Document::class,
            static function (ChildDefinition $definition, Document $attribute): void {
                $definition->addResourceTag('serverless_ogm.document');
            },
        );

        $container->addCompilerPass($this);
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MetadataFactory::class)) {
            return;
        }

        $factory = $container->getDefinition(MetadataFactory::class);

        foreach ($container->findTaggedResourceIds('serverless_ogm.document') as $class => $tags) {
            $factory->addMethodCall('registerClass', [$class]);
        }
    }
}
