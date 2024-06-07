<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190804101439 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $stmt = $this->connection->prepare("SELECT indexname FROM pg_indexes WHERE tablename = 'sylius_order'");
        $result = $stmt->execute();

        $exists = false;
        while ($index = $result->fetchAssociative()) {
            if (strtoupper($index['indexname']) === 'IDX_6196A1F9A393D2FB43625D9F') {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $this->addSql('CREATE INDEX IDX_6196A1F9A393D2FB43625D9F ON sylius_order (state, updated_at)');
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX IDX_6196A1F9A393D2FB43625D9F');
    }
}
