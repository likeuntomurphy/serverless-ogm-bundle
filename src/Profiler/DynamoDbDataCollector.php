<?php

declare(strict_types=1);

namespace Likeuntomurphy\Serverless\OGMBundle\Profiler;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DynamoDbDataCollector extends AbstractDataCollector
{
    public function __construct(
        private readonly DynamoDbLogger $logger,
    ) {
    }

    public static function getTemplate(): ?string
    {
        return '@ServerlessOgm/data_collector/dynamodb.html.twig';
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'operations' => $this->logger->getOperations(),
            'total_time' => $this->logger->getTotalTime(),
            'identity_map_hits' => $this->logger->getIdentityMapHits(),
            'identity_map_misses' => $this->logger->getIdentityMapMisses(),
            'hydration_count' => $this->logger->getHydrationCount(),
        ];
    }

    public function getName(): string
    {
        return 'dynamodb';
    }

    /** @return list<array{operation: string, table: ?string, time: float, consumedCapacity: ?array<string, mixed>, params: array<string, mixed>}> */
    public function getOperations(): array
    {
        /** @var list<array{operation: string, table: ?string, time: float, consumedCapacity: ?array<string, mixed>, params: array<string, mixed>}> */
        return $this->data['operations'];
    }

    public function getTotalTime(): float
    {
        return (float) $this->data['total_time']; // @phpstan-ignore cast.double
    }

    public function getOperationCount(): int
    {
        return \count($this->getOperations());
    }

    public function getIdentityMapHits(): int
    {
        return (int) $this->data['identity_map_hits']; // @phpstan-ignore cast.int
    }

    public function getIdentityMapMisses(): int
    {
        return (int) $this->data['identity_map_misses']; // @phpstan-ignore cast.int
    }

    public function getHydrationCount(): int
    {
        return (int) $this->data['hydration_count']; // @phpstan-ignore cast.int
    }
}
