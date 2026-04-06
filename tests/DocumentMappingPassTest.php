<?php

namespace Likeuntomurphy\Serverless\OGMBundle\Tests;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Likeuntomurphy\Serverless\OGMBundle\ServerlessOgmBundle;
use Likeuntomurphy\Serverless\OGMBundle\Tests\Fixture\TestDocument;
use Likeuntomurphy\Serverless\OGM\Metadata\MetadataFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 *
 * @covers \Likeuntomurphy\Serverless\OGMBundle\ServerlessOgmBundle
 */
class DocumentMappingPassTest extends AbstractCompilerPassTestCase
{
    public function testDocumentClassesAreRegisteredViaResourceTag(): void
    {
        $factory = new Definition(MetadataFactory::class);
        $this->setDefinition(MetadataFactory::class, $factory);

        $definition = new Definition(TestDocument::class);
        $definition->addResourceTag('serverless_ogm.document');
        $this->setDefinition(TestDocument::class, $definition);

        $this->compile();

        $calls = $this->container->getDefinition(MetadataFactory::class)->getMethodCalls();

        $registered = array_map(
            fn (array $call) => $call[1][0],
            array_filter($calls, fn (array $call) => 'registerClass' === $call[0]),
        );

        $this->assertContains(TestDocument::class, $registered);
    }

    public function testNoDocumentsRegisteredWithoutTags(): void
    {
        $factory = new Definition(MetadataFactory::class);
        $this->setDefinition(MetadataFactory::class, $factory);

        $this->compile();

        $calls = $this->container->getDefinition(MetadataFactory::class)->getMethodCalls();
        $registerCalls = array_filter($calls, fn (array $call) => 'registerClass' === $call[0]);

        $this->assertEmpty($registerCalls);
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $bundle = new ServerlessOgmBundle();
        $bundle->build($container);
    }
}
