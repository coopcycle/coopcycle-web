<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190701123308 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE topology.layer DROP CONSTRAINT layer_topology_id_fkey');
        $this->addSql('DROP SEQUENCE topology.topology_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.county_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.state_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.place_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.cousub_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.edges_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.addrfeat_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.faces_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.featnames_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.addr_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.zcta5_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.tract_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.tabblock_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.bg_gid_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.pagc_gaz_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.pagc_lex_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE tiger.pagc_rules_id_seq CASCADE');
        $this->addSql('CREATE TABLE restaurant_pledge (id SERIAL NOT NULL, address_id INT DEFAULT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, state INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_43FEC5A5F5B7AF75 ON restaurant_pledge (address_id)');
        $this->addSql('CREATE INDEX IDX_43FEC5A5A76ED395 ON restaurant_pledge (user_id)');
        $this->addSql('ALTER TABLE restaurant_pledge ADD CONSTRAINT FK_43FEC5A5F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_pledge ADD CONSTRAINT FK_43FEC5A5A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE topology.topology');
        $this->addSql('DROP TABLE topology.layer');
        $this->addSql('DROP TABLE tiger.geocode_settings');
        $this->addSql('DROP TABLE tiger.geocode_settings_default');
        $this->addSql('DROP TABLE tiger.direction_lookup');
        $this->addSql('DROP TABLE tiger.secondary_unit_lookup');
        $this->addSql('DROP TABLE tiger.state_lookup');
        $this->addSql('DROP TABLE tiger.street_type_lookup');
        $this->addSql('DROP TABLE tiger.place_lookup');
        $this->addSql('DROP TABLE tiger.county_lookup');
        $this->addSql('DROP TABLE tiger.countysub_lookup');
        $this->addSql('DROP TABLE tiger.zip_lookup_all');
        $this->addSql('DROP TABLE tiger.zip_lookup_base');
        $this->addSql('DROP TABLE tiger.zip_lookup');
        $this->addSql('DROP TABLE tiger.county');
        $this->addSql('DROP TABLE tiger.state');
        $this->addSql('DROP TABLE tiger.place');
        $this->addSql('DROP TABLE tiger.zip_state');
        $this->addSql('DROP TABLE tiger.zip_state_loc');
        $this->addSql('DROP TABLE tiger.cousub');
        $this->addSql('DROP TABLE tiger.edges');
        $this->addSql('DROP TABLE tiger.addrfeat');
        $this->addSql('DROP TABLE tiger.faces');
        $this->addSql('DROP TABLE tiger.featnames');
        $this->addSql('DROP TABLE tiger.addr');
        $this->addSql('DROP TABLE tiger.zcta5');
        $this->addSql('DROP TABLE tiger.loader_platform');
        $this->addSql('DROP TABLE tiger.loader_variables');
        $this->addSql('DROP TABLE tiger.loader_lookuptables');
        $this->addSql('DROP TABLE tiger.tract');
        $this->addSql('DROP TABLE tiger.tabblock');
        $this->addSql('DROP TABLE tiger.bg');
        $this->addSql('DROP TABLE tiger.pagc_gaz');
        $this->addSql('DROP TABLE tiger.pagc_lex');
        $this->addSql('DROP TABLE tiger.pagc_rules');
        $this->addSql('COMMENT ON COLUMN zone.polygon IS \'(DC2Type:geojson)\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('CREATE SCHEMA tiger');
        $this->addSql('CREATE SCHEMA tiger_data');
        $this->addSql('CREATE SEQUENCE topology.topology_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.county_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.state_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.place_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.cousub_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.edges_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.addrfeat_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.faces_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.featnames_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.addr_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.zcta5_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.tract_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.tabblock_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.bg_gid_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.pagc_gaz_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.pagc_lex_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tiger.pagc_rules_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE topology.topology (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, srid INT NOT NULL, "precision" DOUBLE PRECISION NOT NULL, hasz BOOLEAN DEFAULT \'false\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX topology_name_key ON topology.topology (name)');
        $this->addSql('CREATE TABLE topology.layer (topology_id INT NOT NULL, layer_id INT NOT NULL, schema_name VARCHAR(255) NOT NULL, table_name VARCHAR(255) NOT NULL, feature_column VARCHAR(255) NOT NULL, feature_type INT NOT NULL, level INT DEFAULT 0 NOT NULL, child_id INT DEFAULT NULL, PRIMARY KEY(topology_id, layer_id))');
        $this->addSql('CREATE UNIQUE INDEX layer_schema_name_table_name_feature_column_key ON topology.layer (schema_name, table_name, feature_column)');
        $this->addSql('CREATE INDEX IDX_181D8D68ED697DD5 ON topology.layer (topology_id)');
        $this->addSql('CREATE TABLE tiger.geocode_settings (name TEXT NOT NULL, setting TEXT DEFAULT NULL, unit TEXT DEFAULT NULL, category TEXT DEFAULT NULL, short_desc TEXT DEFAULT NULL, PRIMARY KEY(name))');
        $this->addSql('CREATE TABLE tiger.geocode_settings_default (name TEXT NOT NULL, setting TEXT DEFAULT NULL, unit TEXT DEFAULT NULL, category TEXT DEFAULT NULL, short_desc TEXT DEFAULT NULL, PRIMARY KEY(name))');
        $this->addSql('CREATE TABLE tiger.direction_lookup (name VARCHAR(20) NOT NULL, abbrev VARCHAR(3) DEFAULT NULL, PRIMARY KEY(name))');
        $this->addSql('CREATE INDEX direction_lookup_abbrev_idx ON tiger.direction_lookup (abbrev)');
        $this->addSql('CREATE TABLE tiger.secondary_unit_lookup (name VARCHAR(20) NOT NULL, abbrev VARCHAR(5) DEFAULT NULL, PRIMARY KEY(name))');
        $this->addSql('CREATE INDEX secondary_unit_lookup_abbrev_idx ON tiger.secondary_unit_lookup (abbrev)');
        $this->addSql('CREATE TABLE tiger.state_lookup (st_code INT NOT NULL, name VARCHAR(40) DEFAULT NULL, abbrev VARCHAR(3) DEFAULT NULL, statefp CHAR(2) DEFAULT NULL, PRIMARY KEY(st_code))');
        $this->addSql('CREATE UNIQUE INDEX state_lookup_name_key ON tiger.state_lookup (name)');
        $this->addSql('CREATE UNIQUE INDEX state_lookup_statefp_key ON tiger.state_lookup (statefp)');
        $this->addSql('CREATE UNIQUE INDEX state_lookup_abbrev_key ON tiger.state_lookup (abbrev)');
        $this->addSql('CREATE TABLE tiger.street_type_lookup (name VARCHAR(50) NOT NULL, abbrev VARCHAR(50) DEFAULT NULL, is_hw BOOLEAN DEFAULT \'false\' NOT NULL, PRIMARY KEY(name))');
        $this->addSql('CREATE INDEX street_type_lookup_abbrev_idx ON tiger.street_type_lookup (abbrev)');
        $this->addSql('CREATE TABLE tiger.place_lookup (st_code INT NOT NULL, pl_code INT NOT NULL, state VARCHAR(2) DEFAULT NULL, name VARCHAR(90) DEFAULT NULL, PRIMARY KEY(st_code, pl_code))');
        $this->addSql('CREATE INDEX place_lookup_state_idx ON tiger.place_lookup (state)');
        $this->addSql('CREATE TABLE tiger.county_lookup (st_code INT NOT NULL, co_code INT NOT NULL, state VARCHAR(2) DEFAULT NULL, name VARCHAR(90) DEFAULT NULL, PRIMARY KEY(st_code, co_code))');
        $this->addSql('CREATE INDEX county_lookup_state_idx ON tiger.county_lookup (state)');
        $this->addSql('CREATE TABLE tiger.countysub_lookup (st_code INT NOT NULL, co_code INT NOT NULL, cs_code INT NOT NULL, state VARCHAR(2) DEFAULT NULL, county VARCHAR(90) DEFAULT NULL, name VARCHAR(90) DEFAULT NULL, PRIMARY KEY(st_code, co_code, cs_code))');
        $this->addSql('CREATE INDEX countysub_lookup_state_idx ON tiger.countysub_lookup (state)');
        $this->addSql('CREATE TABLE tiger.zip_lookup_all (zip INT DEFAULT NULL, st_code INT DEFAULT NULL, state VARCHAR(2) DEFAULT NULL, co_code INT DEFAULT NULL, county VARCHAR(90) DEFAULT NULL, cs_code INT DEFAULT NULL, cousub VARCHAR(90) DEFAULT NULL, pl_code INT DEFAULT NULL, place VARCHAR(90) DEFAULT NULL, cnt INT DEFAULT NULL)');
        $this->addSql('CREATE TABLE tiger.zip_lookup_base (zip VARCHAR(5) NOT NULL, state VARCHAR(40) DEFAULT NULL, county VARCHAR(90) DEFAULT NULL, city VARCHAR(90) DEFAULT NULL, statefp VARCHAR(2) DEFAULT NULL, PRIMARY KEY(zip))');
        $this->addSql('CREATE TABLE tiger.zip_lookup (zip INT NOT NULL, st_code INT DEFAULT NULL, state VARCHAR(2) DEFAULT NULL, co_code INT DEFAULT NULL, county VARCHAR(90) DEFAULT NULL, cs_code INT DEFAULT NULL, cousub VARCHAR(90) DEFAULT NULL, pl_code INT DEFAULT NULL, place VARCHAR(90) DEFAULT NULL, cnt INT DEFAULT NULL, PRIMARY KEY(zip))');
        $this->addSql('CREATE TABLE tiger.county (cntyidfp VARCHAR(5) NOT NULL, gid SERIAL NOT NULL, statefp VARCHAR(2) DEFAULT NULL, countyfp VARCHAR(3) DEFAULT NULL, countyns VARCHAR(8) DEFAULT NULL, name VARCHAR(100) DEFAULT NULL, namelsad VARCHAR(100) DEFAULT NULL, lsad VARCHAR(2) DEFAULT NULL, classfp VARCHAR(2) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, csafp VARCHAR(3) DEFAULT NULL, cbsafp VARCHAR(5) DEFAULT NULL, metdivfp VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland BIGINT DEFAULT NULL, awater DOUBLE PRECISION DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(cntyidfp))');
        $this->addSql('CREATE INDEX idx_tiger_county ON tiger.county (countyfp)');
        $this->addSql('CREATE UNIQUE INDEX uidx_county_gid ON tiger.county (gid)');
        $this->addSql('CREATE TABLE tiger.state (statefp VARCHAR(2) NOT NULL, gid SERIAL NOT NULL, region VARCHAR(2) DEFAULT NULL, division VARCHAR(2) DEFAULT NULL, statens VARCHAR(8) DEFAULT NULL, stusps VARCHAR(2) NOT NULL, name VARCHAR(100) DEFAULT NULL, lsad VARCHAR(2) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland BIGINT DEFAULT NULL, awater BIGINT DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(statefp))');
        $this->addSql('CREATE UNIQUE INDEX uidx_tiger_state_stusps ON tiger.state (stusps)');
        $this->addSql('CREATE UNIQUE INDEX uidx_tiger_state_gid ON tiger.state (gid)');
        $this->addSql('CREATE INDEX idx_tiger_state_the_geom_gist ON tiger.state USING gist(the_geom)');
        $this->addSql('CREATE TABLE tiger.place (plcidfp VARCHAR(7) NOT NULL, gid SERIAL NOT NULL, statefp VARCHAR(2) DEFAULT NULL, placefp VARCHAR(5) DEFAULT NULL, placens VARCHAR(8) DEFAULT NULL, name VARCHAR(100) DEFAULT NULL, namelsad VARCHAR(100) DEFAULT NULL, lsad VARCHAR(2) DEFAULT NULL, classfp VARCHAR(2) DEFAULT NULL, cpi VARCHAR(1) DEFAULT NULL, pcicbsa VARCHAR(1) DEFAULT NULL, pcinecta VARCHAR(1) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland BIGINT DEFAULT NULL, awater BIGINT DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(plcidfp))');
        $this->addSql('CREATE UNIQUE INDEX uidx_tiger_place_gid ON tiger.place (gid)');
        $this->addSql('CREATE INDEX tiger_place_the_geom_gist ON tiger.place USING gist(the_geom)');
        $this->addSql('CREATE TABLE tiger.zip_state (zip VARCHAR(5) NOT NULL, stusps VARCHAR(2) NOT NULL, statefp VARCHAR(2) DEFAULT NULL, PRIMARY KEY(zip, stusps))');
        $this->addSql('CREATE TABLE tiger.zip_state_loc (zip VARCHAR(5) NOT NULL, stusps VARCHAR(2) NOT NULL, place VARCHAR(100) NOT NULL, statefp VARCHAR(2) DEFAULT NULL, PRIMARY KEY(zip, stusps, place))');
        $this->addSql('CREATE TABLE tiger.cousub (cosbidfp VARCHAR(10) NOT NULL, gid SERIAL NOT NULL, statefp VARCHAR(2) DEFAULT NULL, countyfp VARCHAR(3) DEFAULT NULL, cousubfp VARCHAR(5) DEFAULT NULL, cousubns VARCHAR(8) DEFAULT NULL, name VARCHAR(100) DEFAULT NULL, namelsad VARCHAR(100) DEFAULT NULL, lsad VARCHAR(2) DEFAULT NULL, classfp VARCHAR(2) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, cnectafp VARCHAR(3) DEFAULT NULL, nectafp VARCHAR(5) DEFAULT NULL, nctadvfp VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland NUMERIC(14, 0) DEFAULT NULL, awater NUMERIC(14, 0) DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(cosbidfp))');
        $this->addSql('CREATE UNIQUE INDEX uidx_cousub_gid ON tiger.cousub (gid)');
        $this->addSql('CREATE INDEX tige_cousub_the_geom_gist ON tiger.cousub USING gist(the_geom)');
        $this->addSql('CREATE TABLE tiger.edges (gid SERIAL NOT NULL, statefp VARCHAR(2) DEFAULT NULL, countyfp VARCHAR(3) DEFAULT NULL, tlid BIGINT DEFAULT NULL, tfidl NUMERIC(10, 0) DEFAULT NULL, tfidr NUMERIC(10, 0) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, fullname VARCHAR(100) DEFAULT NULL, smid VARCHAR(22) DEFAULT NULL, lfromadd VARCHAR(12) DEFAULT NULL, ltoadd VARCHAR(12) DEFAULT NULL, rfromadd VARCHAR(12) DEFAULT NULL, rtoadd VARCHAR(12) DEFAULT NULL, zipl VARCHAR(5) DEFAULT NULL, zipr VARCHAR(5) DEFAULT NULL, featcat VARCHAR(1) DEFAULT NULL, hydroflg VARCHAR(1) DEFAULT NULL, railflg VARCHAR(1) DEFAULT NULL, roadflg VARCHAR(1) DEFAULT NULL, olfflg VARCHAR(1) DEFAULT NULL, passflg VARCHAR(1) DEFAULT NULL, divroad VARCHAR(1) DEFAULT NULL, exttyp VARCHAR(1) DEFAULT NULL, ttyp VARCHAR(1) DEFAULT NULL, deckedroad VARCHAR(1) DEFAULT NULL, artpath VARCHAR(1) DEFAULT NULL, persist VARCHAR(1) DEFAULT NULL, gcseflg VARCHAR(1) DEFAULT NULL, offsetl VARCHAR(1) DEFAULT NULL, offsetr VARCHAR(1) DEFAULT NULL, tnidf NUMERIC(10, 0) DEFAULT NULL, tnidt NUMERIC(10, 0) DEFAULT NULL, the_geom geometry(MULTILINESTRING, 4269) DEFAULT NULL, PRIMARY KEY(gid))');
        $this->addSql('CREATE INDEX idx_tiger_edges_countyfp ON tiger.edges (countyfp)');
        $this->addSql('CREATE INDEX idx_edges_tlid ON tiger.edges (tlid)');
        $this->addSql('CREATE INDEX idx_tiger_edges_the_geom_gist ON tiger.edges USING gist(the_geom)');
        $this->addSql('CREATE TABLE tiger.addrfeat (gid SERIAL NOT NULL, tlid BIGINT DEFAULT NULL, statefp VARCHAR(2) NOT NULL, aridl VARCHAR(22) DEFAULT NULL, aridr VARCHAR(22) DEFAULT NULL, linearid VARCHAR(22) DEFAULT NULL, fullname VARCHAR(100) DEFAULT NULL, lfromhn VARCHAR(12) DEFAULT NULL, ltohn VARCHAR(12) DEFAULT NULL, rfromhn VARCHAR(12) DEFAULT NULL, rtohn VARCHAR(12) DEFAULT NULL, zipl VARCHAR(5) DEFAULT NULL, zipr VARCHAR(5) DEFAULT NULL, edge_mtfcc VARCHAR(5) DEFAULT NULL, parityl VARCHAR(1) DEFAULT NULL, parityr VARCHAR(1) DEFAULT NULL, plus4l VARCHAR(4) DEFAULT NULL, plus4r VARCHAR(4) DEFAULT NULL, lfromtyp VARCHAR(1) DEFAULT NULL, ltotyp VARCHAR(1) DEFAULT NULL, rfromtyp VARCHAR(1) DEFAULT NULL, rtotyp VARCHAR(1) DEFAULT NULL, offsetl VARCHAR(1) DEFAULT NULL, offsetr VARCHAR(1) DEFAULT NULL, the_geom geometry(LINESTRING, 4269) DEFAULT NULL, PRIMARY KEY(gid))');
        $this->addSql('CREATE INDEX idx_addrfeat_tlid ON tiger.addrfeat (tlid)');
        $this->addSql('CREATE INDEX idx_addrfeat_zipr ON tiger.addrfeat (zipr)');
        $this->addSql('CREATE INDEX idx_addrfeat_zipl ON tiger.addrfeat (zipl)');
        $this->addSql('CREATE INDEX idx_addrfeat_geom_gist ON tiger.addrfeat USING gist(the_geom)');
        $this->addSql('CREATE TABLE tiger.faces (gid SERIAL NOT NULL, tfid NUMERIC(10, 0) DEFAULT NULL, statefp00 VARCHAR(2) DEFAULT NULL, countyfp00 VARCHAR(3) DEFAULT NULL, tractce00 VARCHAR(6) DEFAULT NULL, blkgrpce00 VARCHAR(1) DEFAULT NULL, blockce00 VARCHAR(4) DEFAULT NULL, cousubfp00 VARCHAR(5) DEFAULT NULL, submcdfp00 VARCHAR(5) DEFAULT NULL, conctyfp00 VARCHAR(5) DEFAULT NULL, placefp00 VARCHAR(5) DEFAULT NULL, aiannhfp00 VARCHAR(5) DEFAULT NULL, aiannhce00 VARCHAR(4) DEFAULT NULL, comptyp00 VARCHAR(1) DEFAULT NULL, trsubfp00 VARCHAR(5) DEFAULT NULL, trsubce00 VARCHAR(3) DEFAULT NULL, anrcfp00 VARCHAR(5) DEFAULT NULL, elsdlea00 VARCHAR(5) DEFAULT NULL, scsdlea00 VARCHAR(5) DEFAULT NULL, unsdlea00 VARCHAR(5) DEFAULT NULL, uace00 VARCHAR(5) DEFAULT NULL, cd108fp VARCHAR(2) DEFAULT NULL, sldust00 VARCHAR(3) DEFAULT NULL, sldlst00 VARCHAR(3) DEFAULT NULL, vtdst00 VARCHAR(6) DEFAULT NULL, zcta5ce00 VARCHAR(5) DEFAULT NULL, tazce00 VARCHAR(6) DEFAULT NULL, ugace00 VARCHAR(5) DEFAULT NULL, puma5ce00 VARCHAR(5) DEFAULT NULL, statefp VARCHAR(2) DEFAULT NULL, countyfp VARCHAR(3) DEFAULT NULL, tractce VARCHAR(6) DEFAULT NULL, blkgrpce VARCHAR(1) DEFAULT NULL, blockce VARCHAR(4) DEFAULT NULL, cousubfp VARCHAR(5) DEFAULT NULL, submcdfp VARCHAR(5) DEFAULT NULL, conctyfp VARCHAR(5) DEFAULT NULL, placefp VARCHAR(5) DEFAULT NULL, aiannhfp VARCHAR(5) DEFAULT NULL, aiannhce VARCHAR(4) DEFAULT NULL, comptyp VARCHAR(1) DEFAULT NULL, trsubfp VARCHAR(5) DEFAULT NULL, trsubce VARCHAR(3) DEFAULT NULL, anrcfp VARCHAR(5) DEFAULT NULL, ttractce VARCHAR(6) DEFAULT NULL, tblkgpce VARCHAR(1) DEFAULT NULL, elsdlea VARCHAR(5) DEFAULT NULL, scsdlea VARCHAR(5) DEFAULT NULL, unsdlea VARCHAR(5) DEFAULT NULL, uace VARCHAR(5) DEFAULT NULL, cd111fp VARCHAR(2) DEFAULT NULL, sldust VARCHAR(3) DEFAULT NULL, sldlst VARCHAR(3) DEFAULT NULL, vtdst VARCHAR(6) DEFAULT NULL, zcta5ce VARCHAR(5) DEFAULT NULL, tazce VARCHAR(6) DEFAULT NULL, ugace VARCHAR(5) DEFAULT NULL, puma5ce VARCHAR(5) DEFAULT NULL, csafp VARCHAR(3) DEFAULT NULL, cbsafp VARCHAR(5) DEFAULT NULL, metdivfp VARCHAR(5) DEFAULT NULL, cnectafp VARCHAR(3) DEFAULT NULL, nectafp VARCHAR(5) DEFAULT NULL, nctadvfp VARCHAR(5) DEFAULT NULL, lwflag VARCHAR(1) DEFAULT NULL, "offset" VARCHAR(1) DEFAULT NULL, atotal DOUBLE PRECISION DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(gid))');
        $this->addSql('CREATE INDEX idx_tiger_faces_tfid ON tiger.faces (tfid)');
        $this->addSql('CREATE INDEX idx_tiger_faces_countyfp ON tiger.faces (countyfp)');
        $this->addSql('CREATE INDEX tiger_faces_the_geom_gist ON tiger.faces USING gist(the_geom)');
        $this->addSql('CREATE TABLE tiger.featnames (gid SERIAL NOT NULL, tlid BIGINT DEFAULT NULL, fullname VARCHAR(100) DEFAULT NULL, name VARCHAR(100) DEFAULT NULL, predirabrv VARCHAR(15) DEFAULT NULL, pretypabrv VARCHAR(50) DEFAULT NULL, prequalabr VARCHAR(15) DEFAULT NULL, sufdirabrv VARCHAR(15) DEFAULT NULL, suftypabrv VARCHAR(50) DEFAULT NULL, sufqualabr VARCHAR(15) DEFAULT NULL, predir VARCHAR(2) DEFAULT NULL, pretyp VARCHAR(3) DEFAULT NULL, prequal VARCHAR(2) DEFAULT NULL, sufdir VARCHAR(2) DEFAULT NULL, suftyp VARCHAR(3) DEFAULT NULL, sufqual VARCHAR(2) DEFAULT NULL, linearid VARCHAR(22) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, paflag VARCHAR(1) DEFAULT NULL, statefp VARCHAR(2) DEFAULT NULL, PRIMARY KEY(gid))');
        $this->addSql('CREATE INDEX idx_tiger_featnames_tlid_statefp ON tiger.featnames (tlid, statefp)');
        $this->addSql('CREATE TABLE tiger.addr (gid SERIAL NOT NULL, tlid BIGINT DEFAULT NULL, fromhn VARCHAR(12) DEFAULT NULL, tohn VARCHAR(12) DEFAULT NULL, side VARCHAR(1) DEFAULT NULL, zip VARCHAR(5) DEFAULT NULL, plus4 VARCHAR(4) DEFAULT NULL, fromtyp VARCHAR(1) DEFAULT NULL, totyp VARCHAR(1) DEFAULT NULL, fromarmid INT DEFAULT NULL, toarmid INT DEFAULT NULL, arid VARCHAR(22) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, statefp VARCHAR(2) DEFAULT NULL, PRIMARY KEY(gid))');
        $this->addSql('CREATE INDEX idx_tiger_addr_zip ON tiger.addr (zip)');
        $this->addSql('CREATE INDEX idx_tiger_addr_tlid_statefp ON tiger.addr (tlid, statefp)');
        $this->addSql('CREATE TABLE tiger.zcta5 (statefp VARCHAR(2) NOT NULL, zcta5ce VARCHAR(5) NOT NULL, gid SERIAL NOT NULL, classfp VARCHAR(2) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland DOUBLE PRECISION DEFAULT NULL, awater DOUBLE PRECISION DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, partflg VARCHAR(1) DEFAULT NULL, the_geom geometry(GEOMETRY, 4269) DEFAULT NULL, PRIMARY KEY(zcta5ce, statefp))');
        $this->addSql('CREATE UNIQUE INDEX uidx_tiger_zcta5_gid ON tiger.zcta5 (gid)');
        $this->addSql('CREATE TABLE tiger.loader_platform (os VARCHAR(50) NOT NULL, declare_sect TEXT DEFAULT NULL, pgbin TEXT DEFAULT NULL, wget TEXT DEFAULT NULL, unzip_command TEXT DEFAULT NULL, psql TEXT DEFAULT NULL, path_sep TEXT DEFAULT NULL, loader TEXT DEFAULT NULL, environ_set_command TEXT DEFAULT NULL, county_process_command TEXT DEFAULT NULL, PRIMARY KEY(os))');
        $this->addSql('CREATE TABLE tiger.loader_variables (tiger_year VARCHAR(4) NOT NULL, website_root TEXT DEFAULT NULL, staging_fold TEXT DEFAULT NULL, data_schema TEXT DEFAULT NULL, staging_schema TEXT DEFAULT NULL, PRIMARY KEY(tiger_year))');
        $this->addSql('CREATE TABLE tiger.loader_lookuptables (lookup_name TEXT NOT NULL, process_order INT DEFAULT 1000 NOT NULL, table_name TEXT DEFAULT NULL, single_mode BOOLEAN DEFAULT \'true\' NOT NULL, load BOOLEAN DEFAULT \'true\' NOT NULL, level_county BOOLEAN DEFAULT \'false\' NOT NULL, level_state BOOLEAN DEFAULT \'false\' NOT NULL, level_nation BOOLEAN DEFAULT \'false\' NOT NULL, post_load_process TEXT DEFAULT NULL, single_geom_mode BOOLEAN DEFAULT \'false\', insert_mode CHAR(1) DEFAULT \'c\' NOT NULL, pre_load_process TEXT DEFAULT NULL, columns_exclude VARCHAR(255) DEFAULT NULL, website_root_override TEXT DEFAULT NULL, PRIMARY KEY(lookup_name))');
        $this->addSql('COMMENT ON COLUMN tiger.loader_lookuptables.lookup_name IS \'This is the table name to inherit from and suffix of resulting output table -- how the table will be named --  edges here would mean -- ma_edges , pa_edges etc. except in the case of national tables. national level tables have no prefix\'');
        $this->addSql('COMMENT ON COLUMN tiger.loader_lookuptables.table_name IS \'suffix of the tables to load e.g.  edges would load all tables like *edges.dbf(shp)  -- so tl_2010_42129_edges.dbf .  \'');
        $this->addSql('COMMENT ON COLUMN tiger.loader_lookuptables.load IS \'Whether or not to load the table.  For states and zcta5 (you may just want to download states10, zcta510 nationwide file manually) load your own into a single table that inherits from tiger.states, tiger.zcta5.  You\'\'ll get improved performance for some geocoding cases.\'');
        $this->addSql('COMMENT ON COLUMN tiger.loader_lookuptables.level_nation IS \'These are tables that contain all data for the whole US so there is just a single file\'');
        $this->addSql('COMMENT ON COLUMN tiger.loader_lookuptables.columns_exclude IS \'List of columns to exclude as an array. This is excluded from both input table and output table and rest of columns remaining are assumed to be in same order in both tables. gid, geoid,cpi,suffix1ce are excluded if no columns are specified.\'');
        $this->addSql('COMMENT ON COLUMN tiger.loader_lookuptables.website_root_override IS \'Path to use for wget instead of that specified in year table.  Needed currently for zcta where they release that only for 2000 and 2010\'');
        $this->addSql('CREATE TABLE tiger.tract (tract_id VARCHAR(11) NOT NULL, gid SERIAL NOT NULL, statefp VARCHAR(2) DEFAULT NULL, countyfp VARCHAR(3) DEFAULT NULL, tractce VARCHAR(6) DEFAULT NULL, name VARCHAR(7) DEFAULT NULL, namelsad VARCHAR(20) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland DOUBLE PRECISION DEFAULT NULL, awater DOUBLE PRECISION DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(tract_id))');
        $this->addSql('CREATE TABLE tiger.tabblock (tabblock_id VARCHAR(16) NOT NULL, gid SERIAL NOT NULL, statefp VARCHAR(2) DEFAULT NULL, countyfp VARCHAR(3) DEFAULT NULL, tractce VARCHAR(6) DEFAULT NULL, blockce VARCHAR(4) DEFAULT NULL, name VARCHAR(20) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, ur VARCHAR(1) DEFAULT NULL, uace VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland DOUBLE PRECISION DEFAULT NULL, awater DOUBLE PRECISION DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(tabblock_id))');
        $this->addSql('CREATE TABLE tiger.bg (bg_id VARCHAR(12) NOT NULL, gid SERIAL NOT NULL, statefp VARCHAR(2) DEFAULT NULL, countyfp VARCHAR(3) DEFAULT NULL, tractce VARCHAR(6) DEFAULT NULL, blkgrpce VARCHAR(1) DEFAULT NULL, namelsad VARCHAR(13) DEFAULT NULL, mtfcc VARCHAR(5) DEFAULT NULL, funcstat VARCHAR(1) DEFAULT NULL, aland DOUBLE PRECISION DEFAULT NULL, awater DOUBLE PRECISION DEFAULT NULL, intptlat VARCHAR(11) DEFAULT NULL, intptlon VARCHAR(12) DEFAULT NULL, the_geom geometry(MULTIPOLYGON, 4269) DEFAULT NULL, PRIMARY KEY(bg_id))');
        $this->addSql('CREATE TABLE tiger.pagc_gaz (id SERIAL NOT NULL, seq INT DEFAULT NULL, word TEXT DEFAULT NULL, stdword TEXT DEFAULT NULL, token INT DEFAULT NULL, is_custom BOOLEAN DEFAULT \'true\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE tiger.pagc_lex (id SERIAL NOT NULL, seq INT DEFAULT NULL, word TEXT DEFAULT NULL, stdword TEXT DEFAULT NULL, token INT DEFAULT NULL, is_custom BOOLEAN DEFAULT \'true\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE tiger.pagc_rules (id SERIAL NOT NULL, rule TEXT DEFAULT NULL, is_custom BOOLEAN DEFAULT \'true\', PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE topology.layer ADD CONSTRAINT layer_topology_id_fkey FOREIGN KEY (topology_id) REFERENCES topology.topology (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE restaurant_pledge');
        $this->addSql('COMMENT ON COLUMN zone.polygon IS \'(DC2Type:geojson)(DC2Type:geojson)\'');
    }
}
