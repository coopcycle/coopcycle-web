<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180302223910 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        try {

            $contents = Yaml::parseFile(__DIR__ . '/../config/parameters.yml');

            if (isset($contents['parameters']['stripe_publishable_key'])) {
                $this->addSql('INSERT INTO craue_config_setting (name, section, value) VALUES (:name, :section, :value)', [
                    'name' => 'stripe_publishable_key',
                    'section' => 'general',
                    'value' => $contents['parameters']['stripe_publishable_key']
                ]);
            }

            if (isset($contents['parameters']['stripe_secret_key'])) {
                $this->addSql('INSERT INTO craue_config_setting (name, section, value) VALUES (:name, :section, :value)', [
                    'name' => 'stripe_secret_key',
                    'section' => 'general',
                    'value' => $contents['parameters']['stripe_secret_key']
                ]);
            }

            $this->addSql('UPDATE craue_config_setting SET name = :new_name WHERE name = :old_name', [
                'new_name' => 'latlng',
                'old_name' => 'maps.center',
            ]);

        } catch (ParseException $exception) {
            printf('Unable to parse the YAML string: %s', $exception->getMessage());
        }

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('DELETE FROM craue_config_setting WHERE name = \'stripe_publishable_key\'');
        $this->addSql('DELETE FROM craue_config_setting WHERE name = \'stripe_secret_key\'');
        $this->addSql('UPDATE craue_config_setting SET name = :old_name WHERE name = :new_name', [
            'new_name' => 'latlng',
            'old_name' => 'maps.center',
        ]);
    }
}
