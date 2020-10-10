<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201010061644 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_form (id SERIAL NOT NULL, pricing_rule_set_id INT DEFAULT NULL, time_slot_id INT DEFAULT NULL, package_set_id INT DEFAULT NULL, with_vehicle BOOLEAN NOT NULL, with_weight BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_831435D5C213A00E ON delivery_form (pricing_rule_set_id)');
        $this->addSql('CREATE INDEX IDX_831435D5D62B0FA ON delivery_form (time_slot_id)');
        $this->addSql('CREATE INDEX IDX_831435D52E007EC4 ON delivery_form (package_set_id)');
        $this->addSql('ALTER TABLE delivery_form ADD CONSTRAINT FK_831435D5C213A00E FOREIGN KEY (pricing_rule_set_id) REFERENCES pricing_rule_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_form ADD CONSTRAINT FK_831435D5D62B0FA FOREIGN KEY (time_slot_id) REFERENCES time_slot (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_form ADD CONSTRAINT FK_831435D52E007EC4 FOREIGN KEY (package_set_id) REFERENCES package_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT name, value FROM craue_config_setting');
        $stmt->execute();

        $settings = [];
        while ($setting = $stmt->fetch()) {
            $settings[$setting['name']] = $setting['value'];
        }

        if ($settings['embed.delivery.pricingRuleSet'] ?? false) {
            $this->addSql('INSERT INTO delivery_form (pricing_rule_set_id, time_slot_id, package_set_id, with_vehicle, with_weight, created_at, updated_at) VALUES (:pricing_rule_set_id, :time_slot_id, :package_set_id, :with_vehicle, :with_weight, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                'pricing_rule_set_id' => $settings['embed.delivery.pricingRuleSet'],
                'time_slot_id'        => $settings['embed.delivery.timeSlot'] ?? null,
                'package_set_id'      => $settings['embed.delivery.packageSet'] ?? null,
                'with_vehicle'        => (isset($settings['embed.delivery.withVehicle']) ?
                    filter_var($settings['embed.delivery.withVehicle'], FILTER_VALIDATE_BOOLEAN) : false) ? 't' : 'f',
                'with_weight'         => (isset($settings['embed.delivery.withWeight']) ?
                    filter_var($settings['embed.delivery.withWeight'], FILTER_VALIDATE_BOOLEAN) : false) ? 't' : 'f',
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE delivery_form');
    }
}
