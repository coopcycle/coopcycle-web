<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180327140902 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        foreach (['fr', 'en', 'es'] as $locale) {
            $this->addSql('INSERT INTO sylius_locale (code, created_at, updated_at) VALUES (:code, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                'code' => $locale,
            ]);
        }

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
