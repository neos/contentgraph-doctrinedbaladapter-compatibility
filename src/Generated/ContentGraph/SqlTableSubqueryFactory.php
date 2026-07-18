<?php

declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph;

use Neos\ContentGraph\DoctrineDbalAdapter\Compatibility\Generated\ContentGraph\Domain\Projection\ContentStreamLayers;

/**
 * @internal
 */
final readonly class SqlTableSubqueryFactory
{
    private function __construct(
        private ContentGraphTableNames $tableNames,
    ) {
    }

    public static function for(ContentGraphTableNames $tableNames): self
    {
        return new self($tableNames);
    }

    public function forHierarchyRelation(ContentStreamLayers $contentStreamLayers): HierarchyRelationSubquery
    {
        return HierarchyRelationSubquery::create($this->tableNames, $contentStreamLayers);
    }
}
