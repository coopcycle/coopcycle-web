<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240617121101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $stmt = $this->connection->prepare('SELECT id, expression FROM pricing_rule');

        $result = $stmt->execute();

        while ($pricingRule = $result->fetchAssociative()) {

            $expression = $pricingRule['expression'];

            if (str_contains($expression, 'diff_days')
            ||  str_contains($expression, 'diff_hours')
            ||  str_contains($expression, 'time_range_length')) {

                $expression = preg_replace('/(?<func>diff_(hours|days))\(pickup\) (?<expression>([<>]|==|in) [0-9]+(\.\.)*[0-9]*)/', '${1}(pickup, \'${3}\')', $expression);

                $expression = preg_replace('/time_range_length\((?<type>(pickup|dropoff)), \'hours\'\) (?<expression>([<>]|==|in) [0-9]+(\.\.)*[0-9]*)/', 'time_range_length(${1}, \'hours\', \'${3}\')', $expression);

                $this->addSql('UPDATE pricing_rule SET expression = :expression WHERE id = :id', [
                    'expression' => $expression,
                    'id' => $pricingRule['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}
