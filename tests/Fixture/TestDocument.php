<?php

namespace Likeuntomurphy\Serverless\OGMBundle\Tests\Fixture;

use Likeuntomurphy\Serverless\OGM\Mapping\Document;
use Likeuntomurphy\Serverless\OGM\Mapping\Field;
use Likeuntomurphy\Serverless\OGM\Mapping\PartitionKey;

#[Document(table: 'test_docs', pk: 'PK')]
class TestDocument
{
    #[PartitionKey]
    public string $id;

    #[Field]
    public string $name;
}
