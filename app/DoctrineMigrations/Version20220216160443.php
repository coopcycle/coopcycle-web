<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220216160443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $stmt = $this->connection->prepare('SELECT time_slot_id, start_time, end_time FROM time_slot_choice');
        $result = $stmt->execute();

        $timeSlots = [];
        while ($timeSlotChoice = $result->fetchAssociative()) {
            $timeSlots[$timeSlotChoice['time_slot_id']][] = $timeSlotChoice;
        }

        foreach ($timeSlots as $id => $choices) {

            $openingHours = [];
            foreach ($choices as $choice) {
                $openingHours[] = sprintf('Mo-Su %s-%s',
                    substr($choice['start_time'], 0, 5),
                    substr($choice['end_time'], 0, 5)
                );
            }

            $this->addSql('UPDATE time_slot SET opening_hours = :opening_hours WHERE id = :id', [
                'opening_hours' => json_encode($openingHours),
                'id' => $id,
            ]);
        }

        $this->addSql('DROP TABLE time_slot_choice');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
