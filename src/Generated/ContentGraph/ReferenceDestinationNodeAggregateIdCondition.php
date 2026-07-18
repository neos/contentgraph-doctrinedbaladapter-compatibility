<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph;

use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\Dbal\Query\Parameter;
use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\Dbal\Query\Parameters;

/**
 * @internal
 */
final readonly class ReferenceDestinationNodeAggregateIdCondition
{
    private function __construct(
        private NodeAggregateId $nodeAggregateId
    ) {
    }

    public static function forNodeAggregateId(NodeAggregateId $nodeAggregateId): self
    {
        return new self(
            $nodeAggregateId
        );
    }

    public function getParameters(): Parameters
    {
        return Parameters::create(
            Parameter::string('nodeAggregateId', $this->nodeAggregateId->value)
        );
    }

    public function toRelationAnchorPointSubquerySql(ContentGraphTableNames $tableNames): string
    {
        return "(SELECT nodeanchorpoint FROM {$tableNames->referenceRelation()} WHERE destinationnodeaggregateid = :nodeAggregateId)";
    }
}
