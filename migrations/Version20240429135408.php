<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240429135408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_email_address DROP FOREIGN KEY FK_C7383D7A19EB6921');
        $this->addSql('ALTER TABLE client_email_address DROP FOREIGN KEY FK_C7383D7A59045DAA');
        $this->addSql('DROP TABLE client_email_address');
        $this->addSql('ALTER TABLE client ADD email VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client_email_address (client_id INT NOT NULL, email_address_id INT NOT NULL, INDEX IDX_C7383D7A19EB6921 (client_id), INDEX IDX_C7383D7A59045DAA (email_address_id), PRIMARY KEY(client_id, email_address_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE client_email_address ADD CONSTRAINT FK_C7383D7A19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_email_address ADD CONSTRAINT FK_C7383D7A59045DAA FOREIGN KEY (email_address_id) REFERENCES email_address (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client DROP email');
    }
}
