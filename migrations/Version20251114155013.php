<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114155013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position field to produits table for custom ordering';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles CHANGE image_miniature image_miniature LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_bfdd3168989d9b62 ON articles');
        $this->addSql('CREATE UNIQUE INDEX uniq_articles_slug ON articles (slug)');
        $this->addSql('ALTER TABLE categories CHANGE cree_le cree_le DATETIME NOT NULL, CHANGE modifie_le modifie_le DATETIME NOT NULL');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_categories_parent ON categories');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY fk_produits_categorie');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY fk_produits_sous_categorie');
        $this->addSql('DROP INDEX idx_product_reference ON produits');
        $this->addSql('DROP INDEX idx_produits_prix ON produits');
        $this->addSql('DROP INDEX idx_produits_actif_publie ON produits');
        $this->addSql('DROP INDEX idx_product_seo_desc ON produits');
        $this->addSql('DROP INDEX idx_produits_cat_souscat ON produits');
        $this->addSql('DROP INDEX idx_product_titre ON produits');
        $this->addSql('DROP INDEX idx_product_search ON produits');
        $this->addSql('DROP INDEX idx_produits_marque ON produits');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY fk_produits_sous_categorie');
        $this->addSql('ALTER TABLE produits CHANGE description_courte description_courte LONGTEXT DEFAULT NULL, CHANGE devise devise VARCHAR(3) NOT NULL, CHANGE cree_le cree_le DATETIME NOT NULL, CHANGE modifie_le modifie_le DATETIME NOT NULL');
        $this->addSql('ALTER TABLE produits ADD position INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT FK_BE2DDF8CBCF5E72D FOREIGN KEY (categorie_id) REFERENCES categories (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT FK_BE2DDF8C365BF48 FOREIGN KEY (sous_categorie_id) REFERENCES categories (id) ON DELETE RESTRICT');
        $this->addSql('DROP INDEX reference ON produits');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BE2DDF8CAEA34913 ON produits (reference)');
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
        $this->addSql('ALTER TABLE articles CHANGE image_miniature image_miniature TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_articles_slug ON articles');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BFDD3168989D9B62 ON articles (slug)');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE categories CHANGE cree_le cree_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_3af34668727aca70 ON categories');
        $this->addSql('CREATE INDEX idx_categories_parent ON categories (parent_id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY FK_BE2DDF8CBCF5E72D');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY FK_BE2DDF8C365BF48');
        $this->addSql('ALTER TABLE produits DROP FOREIGN KEY FK_BE2DDF8C365BF48');
        $this->addSql('ALTER TABLE produits CHANGE description_courte description_courte TEXT DEFAULT NULL, CHANGE devise devise CHAR(3) DEFAULT \'EUR\' NOT NULL, CHANGE cree_le cree_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE produits DROP COLUMN position');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT fk_produits_categorie FOREIGN KEY (categorie_id) REFERENCES categories (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT fk_produits_sous_categorie FOREIGN KEY (sous_categorie_id) REFERENCES categories (id) ON UPDATE CASCADE');
        $this->addSql('CREATE INDEX idx_product_reference ON produits (reference)');
        $this->addSql('CREATE INDEX idx_produits_prix ON produits (prix)');
        $this->addSql('CREATE INDEX idx_produits_actif_publie ON produits (est_actif, publie_le)');
        $this->addSql('CREATE INDEX idx_product_seo_desc ON produits (seo_description)');
        $this->addSql('CREATE INDEX idx_produits_cat_souscat ON produits (categorie_id, sous_categorie_id)');
        $this->addSql('CREATE INDEX idx_product_titre ON produits (titre)');
        $this->addSql('CREATE INDEX idx_product_search ON produits (titre, reference, seo_description)');
        $this->addSql('CREATE INDEX idx_produits_marque ON produits (marque)');
        $this->addSql('DROP INDEX uniq_be2ddf8caea34913 ON produits');
        $this->addSql('CREATE UNIQUE INDEX reference ON produits (reference)');
        $this->addSql('DROP INDEX idx_be2ddf8c365bf48 ON produits');
        $this->addSql('CREATE INDEX fk_produits_sous_categorie ON produits (sous_categorie_id)');
        $this->addSql('ALTER TABLE produits ADD CONSTRAINT FK_BE2DDF8C365BF48 FOREIGN KEY (sous_categorie_id) REFERENCES categories (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE utilisateurs CHANGE cree_le cree_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX uniq_497b315ee7927c74 ON utilisateurs');
        $this->addSql('CREATE UNIQUE INDEX uniq_utilisateurs_email ON utilisateurs (email)');
    }
}
