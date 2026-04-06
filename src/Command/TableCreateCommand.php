<?php

namespace Likeuntomurphy\Serverless\OGMBundle\Command;

use Likeuntomurphy\Serverless\OGM\DocumentManager;
use Likeuntomurphy\Serverless\OGM\Metadata\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'table:create', description: 'Create DynamoDB tables for all mapped documents')]
final readonly class TableCreateCommand
{
    public function __construct(
        private DocumentManager $dm,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $client = $this->dm->getClient();
        $existing = [];
        $params = [];
        do {
            $result = $client->listTables($params);

            /** @var list<string> $names */
            $names = $result['TableNames'] ?? [];
            $existing = array_merge($existing, $names);
            $params['ExclusiveStartTableName'] = $result['LastEvaluatedTableName'] ?? null;
        } while (null !== $params['ExclusiveStartTableName']);
        $created = 0;

        foreach ($this->dm->getMetadataFactory()->getAllMetadata() as $metadata) {
            $tableName = $this->dm->tableName($metadata->table);

            if (\in_array($tableName, $existing, true)) {
                $io->text(sprintf('Table "%s" already exists, skipping.', $tableName));

                continue;
            }

            \assert(null !== $metadata->partitionKey, sprintf('Document "%s" has no partition key.', $metadata->className));
            $schema = [
                ['AttributeName' => $metadata->partitionKey->attributeName, 'KeyType' => 'HASH'],
            ];
            $attrs = [
                ['AttributeName' => $metadata->partitionKey->attributeName, 'AttributeType' => self::dynamoType($metadata, $metadata->partitionKey->propertyName)],
            ];

            if ($metadata->sortKey) {
                $schema[] = ['AttributeName' => $metadata->sortKey->attributeName, 'KeyType' => 'RANGE'];
                $attrs[] = ['AttributeName' => $metadata->sortKey->attributeName, 'AttributeType' => self::dynamoType($metadata, $metadata->sortKey->propertyName)];
            }

            $client->createTable([
                'TableName' => $tableName,
                'KeySchema' => $schema,
                'AttributeDefinitions' => $attrs,
                'BillingMode' => 'PAY_PER_REQUEST',
            ]);

            $io->text(sprintf('Created table "%s".', $tableName));
            ++$created;
        }

        $io->success(sprintf('Done. %d table(s) created.', $created));

        return Command::SUCCESS;
    }

    private static function dynamoType(ClassMetadata $metadata, string $propertyName): string
    {
        $type = $metadata->reflectionProperties[$propertyName]->getType();

        if ($type instanceof \ReflectionNamedType) {
            return match ($type->getName()) {
                'int', 'float' => 'N',
                default => 'S',
            };
        }

        return 'S';
    }
}
