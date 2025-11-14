<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251114094231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_landing_page field to produits table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits ADD is_landing_page TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits DROP COLUMN is_landing_page');
    }
}
