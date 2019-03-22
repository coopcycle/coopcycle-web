<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190322095923 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE store_address (store_id INT NOT NULL, address_id INT NOT NULL, PRIMARY KEY(store_id, address_id))');
        $this->addSql('CREATE INDEX IDX_14464E66B092A811 ON store_address (store_id)');
        $this->addSql('CREATE INDEX IDX_14464E66F5B7AF75 ON store_address (address_id)');
        $this->addSql('ALTER TABLE store_address ADD CONSTRAINT FK_14464E66B092A811 FOREIGN KEY (store_id) REFERENCES store (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store_address ADD CONSTRAINT FK_14464E66F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT id, default_address_id FROM store');
        $stmt->execute();
        while ($store = $stmt->fetch()) {
            $this->addSql('INSERT INTO store_address (store_id, address_id) VALUES (:store_id, :address_id)', [
                'store_id' => $store['id'],
                'address_id' => $store['default_address_id'],
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE store_address');
    }
}
