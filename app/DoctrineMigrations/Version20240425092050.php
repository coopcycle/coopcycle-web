<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240425092050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes invalid SIRET numbers from database';
    }

    public function up(Schema $schema): void
    {
        $validator = Validation::createValidator();

        $stmt = $this->connection->prepare('SELECT id, additional_properties FROM restaurant');

        $result = $stmt->execute();

        while ($restaurant = $result->fetchAssociative()) {
            $additionalProperties = json_decode($restaurant['additional_properties'], true);

            foreach ($additionalProperties as $key => $item) {
                if ($item['name'] === 'siret') {
                    $siret = $item['value'];
                    if (!empty($siret)) {
                        $siret = preg_replace('/\s+/', '', $siret);
                        $violations = $validator->validate($siret, [ new Assert\Luhn() ]);
                        if (count($violations) > 0) {

                            unset($additionalProperties[$key]);
                            $additionalProperties = array_values($additionalProperties);

                            $this->addSql('UPDATE restaurant SET additional_properties = :additional_properties WHERE id = :id', [
                                'additional_properties' => json_encode($additionalProperties),
                                'id' => $restaurant['id'],
                            ]);
                        }
                    }
                    break;
                }
            }
        }

    }

    public function down(Schema $schema): void
    {

    }
}
