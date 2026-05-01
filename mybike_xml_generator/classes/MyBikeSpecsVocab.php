<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeSpecsVocab
{
    const TABLE = 'mybike_specs_vocab';

    public static function createTable(): void
    {
        Db::getInstance()->execute("
            CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . self::TABLE . "` (
              `api_key`    VARCHAR(64)  NOT NULL COLLATE utf8mb4_bin,
              `label_en`   VARCHAR(128) NOT NULL DEFAULT '',
              `label_lt`   VARCHAR(128) NOT NULL DEFAULT '',
              `filterable` TINYINT(1)   NOT NULL DEFAULT 0,
              `show_full`  TINYINT(1)   NOT NULL DEFAULT 1,
              `sort_order` SMALLINT     NOT NULL DEFAULT 0,
              PRIMARY KEY (`api_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public static function dropTable(): void
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }

    public static function populate(): void
    {
        if ((int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::TABLE . '`'
        ) > 0) {
            return;
        }

        foreach (self::defaultVocab() as $e) {
            Db::getInstance()->execute(
                'INSERT IGNORE INTO `' . _DB_PREFIX_ . self::TABLE . '`
                 (`api_key`,`label_en`,`label_lt`,`filterable`,`show_full`,`sort_order`) VALUES (' .
                "'" . pSQL($e[0]) . "'," .
                "'" . pSQL($e[1]) . "'," .
                "'" . pSQL($e[2]) . "'," .
                (int)$e[3] . ',' .
                (int)$e[4] . ',' .
                (int)$e[5] .
                ')'
            );
        }
    }

    // Keyed by api_key — used in XML builder
    public static function loadAll(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `api_key`,`label_en`,`label_lt`,`filterable`,`show_full`,`sort_order`
             FROM `' . _DB_PREFIX_ . self::TABLE . '`
             ORDER BY `sort_order`, `api_key`'
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['api_key']] = [
                'label_en'   => $row['label_en'],
                'label_lt'   => $row['label_lt'],
                'filterable' => (bool)$row['filterable'],
                'show_full'  => (bool)$row['show_full'],
                'sort_order' => (int)$row['sort_order'],
            ];
        }
        return $map;
    }

    // Flat rows for admin UI table
    public static function getAllRows(): array
    {
        return Db::getInstance()->executeS(
            'SELECT `api_key`,`label_en`,`label_lt`,`filterable`,`show_full`,`sort_order`
             FROM `' . _DB_PREFIX_ . self::TABLE . '`
             ORDER BY `sort_order`, `api_key`'
        );
    }

    public static function saveAll(array $entries): void
    {
        foreach ($entries as $e) {
            Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '`
                 (`api_key`,`label_en`,`label_lt`,`filterable`,`show_full`,`sort_order`) VALUES (' .
                "'" . pSQL($e['api_key']) . "'," .
                "'" . pSQL($e['label_en']) . "'," .
                "'" . pSQL($e['label_lt']) . "'," .
                (int)$e['filterable'] . ',' .
                (int)$e['show_full'] . ',' .
                (int)$e['sort_order'] .
                ') ON DUPLICATE KEY UPDATE
                   `label_en`   = VALUES(`label_en`),
                   `label_lt`   = VALUES(`label_lt`),
                   `filterable` = VALUES(`filterable`),
                   `show_full`  = VALUES(`show_full`),
                   `sort_order` = VALUES(`sort_order`)'
            );
        }
    }

    public static function addEntry(string $apiKey, string $labelEn, string $labelLt, bool $filterable, bool $showFull, int $sortOrder): void
    {
        Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . self::TABLE . '`
             (`api_key`,`label_en`,`label_lt`,`filterable`,`show_full`,`sort_order`) VALUES (' .
            "'" . pSQL($apiKey) . "'," .
            "'" . pSQL($labelEn) . "'," .
            "'" . pSQL($labelLt) . "'," .
            (int)$filterable . ',' .
            (int)$showFull . ',' .
            (int)$sortOrder .
            ')'
        );
    }

    public static function deleteEntry(string $apiKey): void
    {
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `api_key` = \'' . pSQL($apiKey) . '\''
        );
    }

    // -----------------------------------------------------------------------

    private static function defaultVocab(): array
    {
        // [api_key, label_en, label_lt, filterable, show_full, sort_order]
        return [
            // --- Filterable (whitelist) ---
            ['frame',                    'Frame',                         'Rėmas',                              1, 1,  10],
            ['modelyear',                'Model year',                    'Modelio metai',                      1, 1,  20],
            ['wheelsize_front',          'Wheelsize front',               'Priekinių ratų dydis',               1, 1,  30],
            ['wheelsize_rear',           'Wheelsize rear',                'Galinių ratų dydis',                 1, 1,  40],
            ['battery_capacity',         'Battery capacity',              'Baterijos talpa',                    1, 1,  50],
            ['drivetrain_brand',         'Drivetrain brand',              'Pavaros prekės ženklas',             1, 1,  60],
            ['pedals',                   'Pedals',                        'Pedalai',                            1, 1,  70],
            ['bartape_or_grips',         'Bar tape / grips',              'Rankenos',                           1, 1,  80],
            ['mudguards',                'Mudguards',                     'Purvasaugiai',                       1, 1,  90],
            ['Material',                 'Material',                      'Medžiaga',                           1, 1, 100],
            ['Bottom Bracket',           'Bottom Bracket',                'Apatinė ašis',                       1, 1, 110],
            // --- Display only (show_full, not filterable) ---
            ['fork',                     'Fork',                          'Šakė',                               0, 1, 200],
            ['chain',                    'Chain',                         'Grandinė',                           0, 1, 210],
            ['gears',                    'Gears',                         'Pavaros',                            0, 1, 220],
            ['derailleur_rear',          'Rear derailleur',               'Galinis pavarų perjungiklis',        0, 1, 230],
            ['derailleur_front',         'Front derailleur',              'Priekinis pavarų perjungiklis',      0, 1, 240],
            ['shifters',                 'Shifters',                      'Pavarų perjungikliai',               0, 1, 250],
            ['cassette',                 'Cassette',                      'Kasetė',                             0, 1, 260],
            ['crankset',                 'Crankset',                      'Pedalų sistema',                     0, 1, 270],
            ['front_brake',              'Front brake',                   'Priekinis stabdys',                  0, 1, 280],
            ['primary_rear_brake',       'Rear brake',                    'Galinis stabdys',                    0, 1, 290],
            ['tyres',                    'Tyres',                         'Padangos',                           0, 1, 300],
            ['wheels',                   'Wheels',                        'Ratai',                              0, 1, 310],
            ['handlebar',                'Handlebar',                     'Vairas',                             0, 1, 320],
            ['stem',                     'Stem',                          'Vairo iškyša',                       0, 1, 330],
            ['saddle',                   'Saddle',                        'Balnas',                             0, 1, 340],
            ['seatpost',                 'Seatpost',                      'Balnelio atrama',                    0, 1, 350],
            ['headset',                  'Headset',                       'Kaiščio komplektas',                 0, 1, 360],
            ['ebike_type',               'E-bike type',                   'Elektrinio dviračio tipas',          0, 1, 370],
            ['battery_model',            'Battery model',                 'Baterijos modelis',                  0, 1, 380],
            ['charger_model',            'Charger model',                 'Įkroviklio modelis',                 0, 1, 390],
            ['engine_model_name',        'Engine model',                  'Variklio modelis',                   0, 1, 400],
            ['rear_light',               'Rear light',                    'Galinis žibintas',                   0, 1, 410],
            ['front_light',              'Front light',                   'Priekinis žibintas',                 0, 1, 420],
            ['drivetrain_type',          'Drivetrain type',               'Pavaros tipas',                      0, 1, 430],
            ['childseat_possible',       'Child seat possible',           'Galima vaikiška kėdutė',             0, 1, 440],
            ['Color',                    'Color',                         'Spalva',                             0, 1, 450],
            ['Model Year',               'Model Year',                    'Modelio metai',                      0, 1, 460],
            ['Top Tube Length',          'Top Tube Length',               'Viršutinio vamzdžio ilgis',          0, 1, 470],
            ['Version',                  'Version',                       'Versija',                            0, 1, 480],
            ['Size',                     'Size',                          'Dydis',                              0, 1, 490],
            ['Frame',                    'Frame',                         'Rėmas',                              0, 1, 500],
            ['Crankset',                 'Crankset',                      'Pedalų sistema',                     0, 1, 510],
            ['Saddle',                   'Saddle',                        'Balnas',                             0, 1, 520],
            ['Handlebar',                'Handlebar',                     'Vairas',                             0, 1, 530],
            ['Rear Hub',                 'Rear Hub',                      'Galinis stebulys',                   0, 1, 540],
            ['Front Hub',                'Front Hub',                     'Priekinis stebulys',                 0, 1, 550],
            ['Chainwheel',               'Chainwheel',                    'Grandinės žiedas',                   0, 1, 560],
            ['Rim',                      'Rim',                           'Ratlankis',                          0, 1, 570],
            ['Weight',                   'Weight',                        'Svoris',                             0, 1, 580],
            ['Fork',                     'Fork',                          'Šakė',                               0, 1, 590],
            ['Seat Post',                'Seat Post',                     'Balnelio atrama',                    0, 1, 600],
            ['Frame Size',               'Frame Size',                    'Rėmo dydis',                         0, 1, 610],
            // --- Skip (redundant / always null) ---
            ['secondary_rear_brake',     'Secondary rear brake',          'Antrinis galinis stabdys',           0, 0, 700],
            ['ebike_type_description',   'E-bike type description',       'Elektrinio dviračio tipas',          0, 0, 710],
            ['registration_required',    'Registration required',         'Būtina registracija',                0, 0, 720],
            ['riding_posture_description','Riding posture',               'Važiavimo poza',                     0, 0, 730],
        ];
    }
}
