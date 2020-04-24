<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200424061221 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $stmts = [];
        $stmts['product_option'] =
            $this->connection->prepare('SELECT * FROM sylius_product_option');
        $stmts['product_option_value'] =
            $this->connection->prepare('SELECT * FROM sylius_product_option_value WHERE option_id = :option_id');

        $stmts['product_option']->execute();
        while ($productOption = $stmts['product_option']->fetch()) {

            $stmts['product_option_value']->bindParam('option_id', $productOption['id']);
            $stmts['product_option_value']->execute();

            while ($productOptionValue = $stmts['product_option_value']->fetch()) {
                switch ($productOption['strategy']) {
                    case 'free':
                        $this->addSql('UPDATE sylius_product_option_value SET price = 0 WHERE id = :id', [
                            'id' => $productOptionValue['id'],
                        ]);
                        break;
                    case 'option':
                        $this->addSql('UPDATE sylius_product_option_value SET price = :price WHERE id = :id', [
                            'price' => $productOption['price'] !== null ? $productOption['price'] : 0,
                            'id' => $productOptionValue['id'],
                        ]);
                        $this->addSql('UPDATE sylius_product_option SET strategy = :strategy WHERE id = :id', [
                            'strategy' => 'option_value',
                            'id' => $productOption['id'],
                        ]);
                        break;
                    case 'option_value':
                        if ($productOptionValue['price'] === null) {
                            $this->addSql('UPDATE sylius_product_option_value SET price = 0 WHERE id = :id', [
                                'id' => $productOptionValue['id'],
                            ]);
                        }
                        break;
                }
            }
        }

        $this->addSql('ALTER TABLE sylius_product_option_value ALTER price SET NOT NULL');
        $this->addSql('ALTER TABLE sylius_product_option DROP price');

        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT FK_2B5FF009A7C41D6F');
        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT FK_2B5FF0094584665A');

        $this->addSql('ALTER TABLE sylius_product_options ADD CONSTRAINT FK_2B5FF009A7C41D6F FOREIGN KEY (option_id) REFERENCES sylius_product_option (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_options ADD CONSTRAINT FK_2B5FF0094584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_option ADD price INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option_value ALTER price DROP NOT NULL');

        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT fk_2b5ff0094584665a');
        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT fk_2b5ff009a7c41d6f');

        $this->addSql('ALTER TABLE sylius_product_options ADD CONSTRAINT fk_2b5ff0094584665a FOREIGN KEY (product_id) REFERENCES sylius_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_options ADD CONSTRAINT fk_2b5ff009a7c41d6f FOREIGN KEY (option_id) REFERENCES sylius_product_option (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
