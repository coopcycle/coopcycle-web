<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20191023052827 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE pricing_rule SET expression = REPLACE(expression, \'  == \', \' == \')');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
