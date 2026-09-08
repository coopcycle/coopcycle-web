<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908084435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add search_query (saved search bar queries, e.g. /admin/orders)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE search_query (
              id SERIAL NOT NULL,
              user_id INT NOT NULL,
              scope VARCHAR(64) NOT NULL,
              query TEXT NOT NULL,
              name VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_10887602A76ED395AF55D3 ON search_query (user_id, scope)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              search_query
            ADD
              CONSTRAINT FK_10887602A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_query DROP CONSTRAINT FK_10887602A76ED395');
        $this->addSql('DROP TABLE search_query');
    }
}
