<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeCategoryManager
{
    const TABLE = 'mybike_xml_category';

    public static function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_category`   INT(11) NOT NULL,
            `section`       VARCHAR(64) NOT NULL,
            `title`         VARCHAR(128) NOT NULL,
            `product_count` INT(11) NOT NULL DEFAULT 0,
            `enabled`       TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    public static function dropTable()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`'
        );
    }

    // Populate/atnaujinti iš API kategorijų masyvo
    public static function populate(array $categories)
    {
        $db = Db::getInstance();

        foreach ($categories as $cat) {
            $id    = (int)$cat['id'];
            $exists = (int)$db->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE id_category = ' . $id
            );

            $data = [
                'section'       => pSQL($cat['section']),
                'title'         => pSQL($cat['title']),
                'product_count' => (int)$cat['product_count'],
            ];

            if ($exists) {
                $db->update(self::TABLE, $data, 'id_category = ' . $id);
            } else {
                $db->insert(self::TABLE, array_merge(['id_category' => $id, 'enabled' => 1], $data));
            }
        }

        return true;
    }

    // Visos kategorijos sugrupuotos pagal section
    public static function getAllGrouped()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
             ORDER BY section ASC, title ASC'
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['section']][] = $row;
        }

        return $grouped;
    }

    // Tik įjungtų kategorijų ID sąrašas
    public static function getEnabledIds()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_category FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE enabled = 1'
        );

        return array_map('intval', array_column($rows, 'id_category'));
    }

    // Ar lentelė tuščia
    public static function isEmpty()
    {
        return !(int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::TABLE . '`'
        );
    }

    public static function getEnabledProductCount(): int
    {
        return (int)Db::getInstance()->getValue(
            'SELECT SUM(`product_count`) FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `enabled` = 1'
        );
    }

    // Įjungti visas kategorijas (naudojama po pirmo API fetch)
    public static function enableAll()
    {
        return Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . self::TABLE . '` SET `enabled` = 1'
        );
    }

    // Išsaugoti pasirinkimus iš formos (masyvas įjungtų ID)
    public static function saveSelection(array $enabledIds)
    {
        $db = Db::getInstance();
        $enabledIds = array_map('intval', $enabledIds);

        // Išjungti visas
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . self::TABLE . '` SET enabled = 0'
        );

        if (!empty($enabledIds)) {
            $db->execute(
                'UPDATE `' . _DB_PREFIX_ . self::TABLE . '`
                 SET enabled = 1
                 WHERE id_category IN (' . implode(',', $enabledIds) . ')'
            );
        }

        return true;
    }
}
