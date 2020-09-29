<?php

namespace AppBundle\Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSQL94Platform as BasePostgreSQL94Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;

/**
 * @link https://www.postgresql.org/docs/current/pgtrgm.html#id-1.11.7.40.8
 * @link https://stackoverflow.com/questions/38111972/how-to-create-a-multicolumn-gist-index-in-postgresql
 * CREATE TABLE test_trgm (t text);
 * CREATE INDEX trgm_idx ON test_trgm USING GIST (t gist_trgm_ops);
 * CREATE [UNIQUE] INDEX <$name> ON <table> [USING <$method>] ( <$columns> ) [WHERE <$where>]
 */
class PostgreSQL94Platform extends BasePostgreSQL94Platform
{
    public function getIndexFieldDeclarationListSQL($columnsOrIndex): string
    {
        if ($columnsOrIndex instanceof Index && $columnsOrIndex->hasFlag('fulltext')) {

            return implode(', ', array_map(
                fn($column) => sprintf('%s gist_trgm_ops', $column),
                $columnsOrIndex->getQuotedColumns($this)
            ));
        }

        return parent::getIndexFieldDeclarationListSQL($columnsOrIndex);
    }


    public function getCreateIndexSQL(Index $index, $table)
    {
        if (!$index->hasFlag('fulltext')) {
            return parent::getCreateIndexSQL($index, $table);
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        $table = sprintf('%s USING GIST', $table);

        return parent::getCreateIndexSQL($index, $table);
    }
}
