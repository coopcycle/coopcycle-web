<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240627071042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_rule ADD level VARCHAR(255) DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT id, strategy, options FROM pricing_rule_set');

        $result = $stmt->execute();

        while ($pricingRuleSet = $result->fetchAssociative()) {

            $options = json_decode($pricingRuleSet['options'], true);

            if (is_array($options) && in_array('map_all_tasks', $options)) {
                $this->addSql('UPDATE pricing_rule SET level = :level WHERE rule_set_id = :rule_set_id', [
                    'level' => 'task',
                    'rule_set_id' => $pricingRuleSet['id'],
                ]);
            } else {
                $this->addSql('UPDATE pricing_rule SET level = :level WHERE rule_set_id = :rule_set_id', [
                    'level' => 'delivery',
                    'rule_set_id' => $pricingRuleSet['id'],
                ]);
            }
        }

        $this->addSql('ALTER TABLE pricing_rule ALTER level SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_rule DROP level');
    }
}
