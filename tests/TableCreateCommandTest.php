<?php

namespace Likeuntomurphy\Serverless\OGMBundle\Tests;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use Likeuntomurphy\Serverless\OGM\DocumentManager;
use Likeuntomurphy\Serverless\OGM\Metadata\MetadataFactory;
use Likeuntomurphy\Serverless\OGMBundle\Command\TableCreateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class TableCreateCommandTest extends TestCase
{
    public function testDefaultBillingModeIsPayPerRequest(): void
    {
        $calls = [];
        $command = $this->buildCommand('PAY_PER_REQUEST', [], $calls);

        $command->__invoke($this->createStub(SymfonyStyle::class));

        $this->assertCount(1, $calls);
        $this->assertSame('PAY_PER_REQUEST', $calls[0]['BillingMode']);
        $this->assertArrayNotHasKey('ProvisionedThroughput', $calls[0]);
    }

    public function testGlobalProvisionedMode(): void
    {
        $calls = [];
        $command = $this->buildCommand('PROVISIONED', [], $calls);

        $command->__invoke($this->createStub(SymfonyStyle::class));

        $this->assertSame('PROVISIONED', $calls[0]['BillingMode']);
        $this->assertSame(5, $calls[0]['ProvisionedThroughput']['ReadCapacityUnits']);
        $this->assertSame(5, $calls[0]['ProvisionedThroughput']['WriteCapacityUnits']);
    }

    public function testPerTableOverridesGlobalDefault(): void
    {
        $calls = [];
        $command = $this->buildCommand('PAY_PER_REQUEST', [
            'test_docs' => [
                'billing_mode' => 'PROVISIONED',
                'rcu' => 10,
                'wcu' => 20,
            ],
        ], $calls);

        $command->__invoke($this->createStub(SymfonyStyle::class));

        $this->assertSame('PROVISIONED', $calls[0]['BillingMode']);
        $this->assertSame(10, $calls[0]['ProvisionedThroughput']['ReadCapacityUnits']);
        $this->assertSame(20, $calls[0]['ProvisionedThroughput']['WriteCapacityUnits']);
    }

    public function testPerTablePayPerRequestOverridesGlobalProvisioned(): void
    {
        $calls = [];
        $command = $this->buildCommand('PROVISIONED', [
            'test_docs' => [
                'billing_mode' => 'PAY_PER_REQUEST',
                'rcu' => 5,
                'wcu' => 5,
            ],
        ], $calls);

        $command->__invoke($this->createStub(SymfonyStyle::class));

        $this->assertSame('PAY_PER_REQUEST', $calls[0]['BillingMode']);
        $this->assertArrayNotHasKey('ProvisionedThroughput', $calls[0]);
    }

    public function testProvisionedDefaultsToFiveFive(): void
    {
        $calls = [];
        $command = $this->buildCommand('PAY_PER_REQUEST', [
            'test_docs' => [
                'billing_mode' => 'PROVISIONED',
                'rcu' => 5,
                'wcu' => 5,
            ],
        ], $calls);

        $command->__invoke($this->createStub(SymfonyStyle::class));

        $this->assertSame(5, $calls[0]['ProvisionedThroughput']['ReadCapacityUnits']);
        $this->assertSame(5, $calls[0]['ProvisionedThroughput']['WriteCapacityUnits']);
    }

    /**
     * @param array<string, array{billing_mode: string, rcu: int, wcu: int}> $tableConfig
     * @param list<array<string, mixed>>                                      $calls
     */
    private function buildCommand(string $defaultBillingMode, array $tableConfig, array &$calls): TableCreateCommand
    {
        $client = $this->createStub(DynamoDbClient::class);
        $client->method('__call')->willReturnCallback(function (string $name, array $args) use (&$calls) {
            if ('listTables' === $name) {
                return new Result(['TableNames' => []]);
            }
            if ('createTable' === $name) {
                $calls[] = $args[0];

                return new Result([]);
            }

            return new Result([]);
        });

        $metadataFactory = new MetadataFactory();
        $metadataFactory->registerClass(Fixture\TestDocument::class);

        $dm = new DocumentManager($client, $metadataFactory);

        return new TableCreateCommand($dm, $defaultBillingMode, $tableConfig);
    }
}
