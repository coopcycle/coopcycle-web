<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230807105004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD date DATE');
        $this->addSql('CREATE INDEX IDX_6AD1F969AA9E377A ON tour (date)');      

        // Get a Task from each Tour to set Tour's date to Task's `done_after` date. 
        $stmt = $this->connection->prepare('select distinct on (tour_id) tour_id, id, done_after from task where tour_id is not null;');
        $stmt->execute();

        while ($row = $stmt->fetch()) {

            $date = substr($row['done_after'], 0, 10);

            $this->addSql('UPDATE tour SET date = :date WHERE id = :id', [
                'date' => $date,
                'id' => $row['tour_id'],
            ]);
        }

        $this->addSql('ALTER TABLE tour ALTER COLUMN date SET NOT NULL');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP date');
    }
}
