<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240408145652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A29BE3DB2B7');
        $this->addSql('ALTER TABLE ligne_facture CHANGE prestataire_id prestataire_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A29BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES prestataire (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A29BE3DB2B7');
        $this->addSql('ALTER TABLE ligne_facture CHANGE prestataire_id prestataire_id INT NOT NULL');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A29BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES prestataire (id)');
    }
}
