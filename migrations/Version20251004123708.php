<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251004123708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP INDEX idx_articles_statut_publie ON articles');
        $this->addSql('ALTER TABLE articles CHANGE extrait extrait LONGTEXT DEFAULT NULL, CHANGE statut statut VARCHAR(20) NOT NULL, CHANGE cree_le cree_le DATETIME NOT NULL, CHANGE modifie_le modifie_le DATETIME NOT NULL');
        $this->addSql('DROP INDEX uniq_articles_slug ON articles');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BFDD3168989D9B62 ON articles (slug)');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY fk_categories_parent');
        $this->addSql('DROP INDEX idx_categories_active ON categories');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY fk_categories_parent');
        $this->addSql('ALTER TABLE categories CHANGE cree_le cree_le DATETIME NOT NULL, CHANGE modifie_le modifie_le DATETIME NOT NULL');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX uniq_categories_slug ON categories');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF34668989D9B62 ON categories (slug)');
        //$this->addSql('DROP INDEX idx_categories_parent ON categories');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY fk_produits_categorie');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY fk_produits_sous_categorie');
        $this->addSql('DROP INDEX idx_produits_marque ON produits');
        $this->addSql('DROP INDEX idx_produits_prix ON produits');
        $this->addSql('DROP INDEX idx_produits_actif_publie ON produits');
        $this->addSql('DROP INDEX idx_produits_cat_souscat ON produits');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY fk_produits_sous_categorie');
        $this->addSql('ALTER TABLE produits CHANGE description_courte description_courte LONGTEXT DEFAULT NULL, CHANGE devise devise VARCHAR(3) NOT NULL, CHANGE cree_le cree_le DATETIME NOT NULL, CHANGE modifie_le modifie_le DATETIME NOT NULL');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT FK_BE2DDF8CBCF5E72D FOREIGN KEY (categorie_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT FK_BE2DDF8C365BF48 FOREIGN KEY (sous_categorie_id) REFERENCES categories (id)');
        $this->addSql('DROP INDEX uniq_produits_slug ON produits');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BE2DDF8C989D9B62 ON produits (slug)');
        $this->addSql('DROP INDEX uniq_produits_sku ON produits');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BE2DDF8CF9038C4 ON produits (sku)');
        $this->addSql('DROP INDEX fk_produits_sous_categorie ON produits');
        $this->addSql('CREATE INDEX IDX_BE2DDF8C365BF48 ON produits (sous_categorie_id)');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT fk_produits_sous_categorie FOREIGN KEY (sous_categorie_id) REFERENCES categories (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE utilisateurs CHANGE cree_le cree_le DATETIME NOT NULL, CHANGE modifie_le modifie_le DATETIME NOT NULL');
        $this->addSql('DROP INDEX uniq_utilisateurs_email ON utilisateurs');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_497B315EE7927C74 ON utilisateurs (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE articles CHANGE extrait extrait TEXT DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT \'brouillon\' NOT NULL, CHANGE cree_le cree_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_articles_statut_publie ON articles (statut, publie_le)');
        $this->addSql('DROP INDEX uniq_bfdd3168989d9b62 ON articles');
        $this->addSql('CREATE UNIQUE INDEX uniq_articles_slug ON articles (slug)');
        //$this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF34668727ACA70');
        //$this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE categories CHANGE cree_le cree_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_categories_active ON categories (est_active)');
        $this->addSql('DROP INDEX uniq_3af34668989d9b62 ON categories');
        $this->addSql('CREATE UNIQUE INDEX uniq_categories_slug ON categories (slug)');
        $this->addSql('DROP INDEX idx_3af34668727aca70 ON categories');
        $this->addSql('CREATE INDEX idx_categories_parent ON categories (parent_id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY FK_BE2DDF8CBCF5E72D');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY FK_BE2DDF8C365BF48');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY FK_BE2DDF8C365BF48');
        $this->addSql('ALTER TABLE produits CHANGE description_courte description_courte TEXT DEFAULT NULL, CHANGE devise devise CHAR(3) DEFAULT \'EUR\' NOT NULL, CHANGE cree_le cree_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT fk_produits_categorie FOREIGN KEY (categorie_id) REFERENCES categories (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT fk_produits_sous_categorie FOREIGN KEY (sous_categorie_id) REFERENCES categories (id) ON UPDATE CASCADE');
        $this->addSql('CREATE INDEX idx_produits_marque ON produits (marque)');
        $this->addSql('CREATE INDEX idx_produits_prix ON produits (prix)');
        $this->addSql('CREATE INDEX idx_produits_actif_publie ON produits (est_actif, publie_le)');
        $this->addSql('CREATE INDEX idx_produits_cat_souscat ON produits (categorie_id, sous_categorie_id)');
        $this->addSql('DROP INDEX uniq_be2ddf8c989d9b62 ON produits');
        $this->addSql('CREATE UNIQUE INDEX uniq_produits_slug ON produits (slug)');
        $this->addSql('DROP INDEX uniq_be2ddf8cf9038c4 ON produits');
        $this->addSql('CREATE UNIQUE INDEX uniq_produits_sku ON produits (sku)');
        $this->addSql('DROP INDEX idx_be2ddf8c365bf48 ON produits');
        $this->addSql('CREATE INDEX fk_produits_sous_categorie ON produits (sous_categorie_id)');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT FK_BE2DDF8C365BF48 FOREIGN KEY (sous_categorie_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE utilisateurs CHANGE cree_le cree_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX uniq_497b315ee7927c74 ON utilisateurs');
        $this->addSql('CREATE UNIQUE INDEX uniq_utilisateurs_email ON utilisateurs (email)');
    }
}
