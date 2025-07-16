<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250716081834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->tablesAndColumnsToBeUpdated() as [$table, $column]) {
            $this->changeTypesFromLongtextToJsonAndEncodeSerializedData($table, $column);
        }
    }

    public function down(Schema $schema): void
    {
        foreach ($this->tablesAndColumnsToBeUpdated() as [$table, $column]) {
            $this->changeTypesFromJsonToText($table, $column);
        }
    }

    public function postDown(Schema $schema): void
    {
        foreach ($this->tablesAndColumnsToBeUpdated() as [$table, $column]) {
            $this->serializeEncodedData($table, $column);
        }
    }

    private function changeTypesFromLongtextToJsonAndEncodeSerializedData(string $table, string $dataColumn): void
    {
        $connection = $this->connection;
        $rows = $connection->fetchAllAssociative(sprintf('SELECT %s, %s FROM %s', 'id', $dataColumn, $table));

        foreach ($rows as $row) {
            $id = $row['id'];
            $data = $row[$dataColumn];

            $this->skipIf(@unserialize($data) === false, sprintf('Data in %s is not serialized', $table));

            $encodedData = unserialize($data);
            $encodedData = json_encode($encodedData);

            $this->addSql(sprintf('UPDATE %s SET %s = :data WHERE id = :id', $table, $dataColumn), [
                'data' => $encodedData,
                'id' => $id,
            ]);
        }

        $this->addSql(sprintf('ALTER TABLE %s ALTER %s TYPE JSONB USING %s::jsonb', $table, $dataColumn, $dataColumn));
        $this->addSql(sprintf('COMMENT ON COLUMN %s.%s IS NULL', $table, $dataColumn));
    }

    private function changeTypesFromJsonToText(string $table, string $dataColumn): void
    {
        $this->addSql(sprintf('ALTER TABLE %s ALTER %s TYPE TEXT', $table, $dataColumn));
        $this->addSql(sprintf('COMMENT ON COLUMN %s.%s IS \'(DC2Type:array)\'', $table, $dataColumn));
    }

    private function serializeEncodedData(string $table, string $dataColumn): void
    {
        $connection = $this->connection;
        $rows = $connection->fetchAllAssociative(sprintf('SELECT %s, %s FROM %s', 'id', $dataColumn, $table));

        foreach ($rows as $row) {
            $id = $row['id'];
            $data = $row[$dataColumn];

            $this->skipIf(@json_decode($data) === false, sprintf('Data in %s is not json', $table));
            $decodedData = json_decode($data, true);
            $decodedData = serialize($decodedData);

            $this->addSql(sprintf('UPDATE %s SET %s = :data WHERE id = :id', $table, $dataColumn), [
                'data' => $decodedData,
                'id' => $id,
            ]);
        }
    }

    /**
     * @return iterable<array{string, string}>
     */
    private function tablesAndColumnsToBeUpdated(): iterable
    {
        yield ['sylius_product_attribute', 'configuration'];
    }
}
