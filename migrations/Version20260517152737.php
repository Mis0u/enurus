<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517152737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE muscle_group ADD svg_ids JSON NOT NULL DEFAULT '[]'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('upper-pectoralis', 'mid-lower-pectoralis') WHERE name = 'name.chest'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('lats') WHERE name = 'name.lats'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('upper-trapezius', 'lower-trapezius', 'traps-middle') WHERE name = 'name.traps'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('traps-middle') WHERE name = 'name.traps_middle'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('lowerback') WHERE name = 'name.lower_back'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('anterior-deltoid') WHERE name = 'name.anterior_deltoid'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('lateral-deltoid') WHERE name = 'name.lateral_deltoid'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('posterior-deltoid') WHERE name = 'name.posterior_deltoid'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('long-head-bicep', 'short-head-bicep') WHERE name = 'name.biceps'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('lateral-head-triceps', 'long-head-triceps', 'medial-head-triceps') WHERE name = 'name.triceps'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('wrist-flexors', 'wrist-extensors') WHERE name = 'name.forearms'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('upper-abdominals', 'lower-abdominals') WHERE name = 'name.abs'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('obliques') WHERE name = 'name.obliques'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('gluteus-maximus', 'gluteus-medius') WHERE name = 'name.glutes'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('rectus-femoris', 'outer-quadricep', 'inner-quadricep') WHERE name = 'name.quads'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('lateral-hamstrings', 'medial-hamstrings') WHERE name = 'name.hamstrings'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('inner-thigh') WHERE name = 'name.adductors'");
        $this->addSql("UPDATE muscle_group SET svg_ids = JSON_ARRAY('gastrocnemius', 'soleus', 'tibialis') WHERE name = 'name.calves'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE muscle_group DROP COLUMN svg_ids");
    }
}
