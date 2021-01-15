<?php

declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200412120132 extends AbstractMigration
{
    private $mapping = [
        'restaurant' => FoodEstablishment::RESTAURANT,
        'store'      => Store::GROCERY_STORE,
    ];

    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        foreach ($this->mapping as $old => $new) {
            $this->addSql('UPDATE restaurant SET type = :new_type WHERE type = :old_type', [
                'new_type' => $new,
                'old_type' => $old,
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        foreach ($this->mapping as $old => $new) {
            $this->addSql('UPDATE restaurant SET type = :old_type WHERE type = :new_type', [
                'new_type' => $new,
                'old_type' => $old,
            ]);
        }
    }
}
