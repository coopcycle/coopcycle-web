<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250522125927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use integers for "packages.totalVolumeUnits()" pricing expressions using "in" operator.';
    }

    public function up(Schema $schema): void
    {
        $stmt = $this->connection->prepare('SELECT id, expression FROM pricing_rule');

        $result = $stmt->execute();
        while ($pricingRule = $result->fetchAssociative()) {

            if (1 === preg_match('/packages\.totalVolumeUnits\(\) in ([0-9\.]+)/', $pricingRule['expression'], $matches)) {

                $range = $matches[1];

                if (1 === preg_match('/([0-9\.]+)\.\.([0-9\.]+)/', $range, $rangeMatches)) {

                    [ $whole, $start, $end ] = $rangeMatches;

                    $hasFloat = false;

                    if (false !== strpos($start, '.')) {
                        $hasFloat = true;
                        $start = (int) round((float) $start, 0, PHP_ROUND_HALF_ODD);
                    }

                    if (false !== strpos($end, '.')) {
                        $hasFloat = true;
                        $end = (int) round((float) $end, 0, PHP_ROUND_HALF_ODD);
                    }

                    if ($hasFloat) {
                        $expression = str_replace($matches[0], sprintf('packages.totalVolumeUnits() in %d..%d', $start, $end), $pricingRule['expression']);

                        $this->addSql('UPDATE pricing_rule SET expression = :expression WHERE id = :id', [
                            'expression' => $expression,
                            'id' => $pricingRule['id']
                        ]);
                    }
                }
            }
        }

    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
