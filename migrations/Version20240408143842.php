<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240408143842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, numtel VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, contrat VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facture (id INT AUTO_INCREMENT NOT NULL, client_id INT DEFAULT NULL, num_facture VARCHAR(255) NOT NULL, date_facturation DATE NOT NULL, date_echeance DATE NOT NULL, etat VARCHAR(20) NOT NULL, total_ttc DOUBLE PRECISION DEFAULT NULL, totaltaxe DOUBLE PRECISION DEFAULT NULL, INDEX IDX_FE86641019EB6921 (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ligne_facture (id INT AUTO_INCREMENT NOT NULL, facture_id INT DEFAULT NULL, service_id INT DEFAULT NULL, prestataire_id INT NOT NULL, description VARCHAR(255) NOT NULL, prix_unitaire DOUBLE PRECISION DEFAULT NULL, montant_total_ht DOUBLE PRECISION DEFAULT NULL, taxe_tva DOUBLE PRECISION DEFAULT NULL, nb_jours DOUBLE PRECISION DEFAULT NULL, INDEX IDX_611F5A297F2DEE08 (facture_id), INDEX IDX_611F5A29ED5CA9E6 (service_id), INDEX IDX_611F5A29BE3DB2B7 (prestataire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prestataire (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, numtel VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, date_de_naissance DATE NOT NULL, carte_bancaire VARCHAR(255) NOT NULL, poste VARCHAR(255) NOT NULL, nb_jours DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE responsable (id INT AUTO_INCREMENT NOT NULL, nom_responsable VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, numtel VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, age VARCHAR(255) NOT NULL, carte_bancaire VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, prix_unitaire_ht DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641019EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A297F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A29ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A29BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES prestataire (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641019EB6921');
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A297F2DEE08');
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A29ED5CA9E6');
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A29BE3DB2B7');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE ligne_facture');
        $this->addSql('DROP TABLE prestataire');
        $this->addSql('DROP TABLE responsable');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
