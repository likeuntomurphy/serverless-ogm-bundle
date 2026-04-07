<?php

declare(strict_types=1);

namespace Likeuntomurphy\Serverless\OGMBundle\Profiler;

use Aws\CommandInterface;
use Aws\DynamoDb\DynamoDbClient;
use Aws\ResultInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Likeuntomurphy\Serverless\OGM\ProfilingLogger;
use Psr\Http\Message\RequestInterface;

class DynamoDbLogger implements ProfilingLogger
{
    /** @var list<array{operation: string, table: ?string, time: float, consumedCapacity: ?array<string, mixed>, params: array<string, mixed>}> */
    private array $operations = [];

    private int $identityMapHits = 0;
    private int $identityMapMisses = 0;
    private int $hydrationCount = 0;

    public function register(DynamoDbClient $client): void
    {
        $logger = $this;

        $client->getHandlerList()->appendSign(
            static function (callable $handler) use ($logger) {
                return static function (CommandInterface $cmd, ?RequestInterface $req = null) use ($handler, $logger) {
                    $start = microtime(true);

                    /** @var ?string $table */
                    $table = $cmd['TableName'] ?? null;

                    /** @var array<string, mixed> $params */
                    $params = $cmd->toArray();

                    $logger->operations[] = [
                        'operation' => $cmd->getName(),
                        'table' => $table,
                        'time' => 0.0,
                        'consumedCapacity' => null,
                        'params' => $params,
                    ];

                    $idx = array_key_last($logger->operations);

                    /** @var PromiseInterface $promise */
                    $promise = $handler($cmd, $req);

                    return $promise->then(
                        static function (ResultInterface $result) use ($logger, $idx, $start) {
                            $logger->operations[$idx]['time'] = microtime(true) - $start;

                            /** @var null|array<string, mixed> $capacity */
                            $capacity = $result['ConsumedCapacity'] ?? null;
                            if (is_array($capacity)) {
                                $logger->operations[$idx]['consumedCapacity'] = $capacity;
                            }

                            return $result;
                        },
                        static function ($reason) use ($logger, $idx, $start) {
                            $logger->operations[$idx]['time'] = microtime(true) - $start;

                            return Create::rejectionFor($reason);
                        },
                    );
                };
            },
            'dynamodb_profiler',
        );
    }

    public function recordIdentityMapHit(): void
    {
        ++$this->identityMapHits;
    }

    public function recordIdentityMapMiss(): void
    {
        ++$this->identityMapMisses;
    }

    public function recordHydration(): void
    {
        ++$this->hydrationCount;
    }

    /** @return list<array{operation: string, table: ?string, time: float, consumedCapacity: ?array<string, mixed>, params: array<string, mixed>}> */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getIdentityMapHits(): int
    {
        return $this->identityMapHits;
    }

    public function getIdentityMapMisses(): int
    {
        return $this->identityMapMisses;
    }

    public function getHydrationCount(): int
    {
        return $this->hydrationCount;
    }

    public function getTotalTime(): float
    {
        return array_sum(array_column($this->operations, 'time'));
    }

    public function reset(): void
    {
        $this->operations = [];
        $this->identityMapHits = 0;
        $this->identityMapMisses = 0;
        $this->hydrationCount = 0;
    }
}
