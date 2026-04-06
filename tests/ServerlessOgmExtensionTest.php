<?php

namespace Likeuntomurphy\Serverless\OGMBundle\Tests;

use Aws\DynamoDb\DynamoDbClient;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Likeuntomurphy\Serverless\OGMBundle\ServerlessOgmBundle;
use Likeuntomurphy\Serverless\OGM\DocumentManager;
use Likeuntomurphy\Serverless\OGM\Metadata\MetadataFactory;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @covers \Likeuntomurphy\Serverless\OGMBundle\ServerlessOgmBundle
 */
class ServerlessOgmExtensionTest extends AbstractExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->setParameter('kernel.environment', 'test');
        $this->container->setParameter('kernel.project_dir', '/app');
    }

    public function testDocumentManagerIsRegistered(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService(DocumentManager::class);
    }

    public function testDynamoDbClientIsRegistered(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService(DynamoDbClient::class);
    }

    public function testMetadataFactoryIsRegistered(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService(MetadataFactory::class);
    }

    public function testDocumentManagerReceivesMetadataFactory(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            DocumentManager::class,
            1,
            new Reference(MetadataFactory::class),
        );
    }

    public function testCustomEndpoint(): void
    {
        $this->load([
            'dynamodb' => [
                'endpoint' => 'http://dynamodb:8000',
            ],
        ]);

        $definition = $this->container->getDefinition(DynamoDbClient::class);
        $config = $definition->getArgument(0);

        $this->assertSame('http://dynamodb:8000', $config['endpoint']);
    }

    public function testDefaultRegion(): void
    {
        $this->load();

        $definition = $this->container->getDefinition(DynamoDbClient::class);
        $config = $definition->getArgument(0);

        $this->assertSame('us-east-1', $config['region']);
    }

    public function testDocumentManagerHasResetTag(): void
    {
        $this->load();

        $definition = $this->container->getDefinition(DocumentManager::class);

        $this->assertTrue($definition->hasTag('kernel.reset'));
        $this->assertSame('clear', $definition->getTag('kernel.reset')[0]['method']);
    }

    protected function getContainerExtensions(): array
    {
        $bundle = new ServerlessOgmBundle();
        $extension = $bundle->getContainerExtension();
        \assert(null !== $extension);

        return [$extension];
    }
}
