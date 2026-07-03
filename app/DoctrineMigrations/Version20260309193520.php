<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260309193520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Farewell non profits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT fk_6196a1f9e24e94e2');
        $this->addSql('DROP SEQUENCE nonprofit_id_seq CASCADE');
        $this->addSql('DROP TABLE nonprofit');
        $this->addSql('ALTER TABLE api_user DROP default_nonprofit');
        $this->addSql('DROP INDEX idx_6196a1f9e24e94e2');
        $this->addSql('ALTER TABLE sylius_order DROP nonprofit_id');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
