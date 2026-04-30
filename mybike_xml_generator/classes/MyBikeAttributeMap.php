<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeAttributeMap
{
    const GROUP_NAME = 'Dydis';

    private $groupId = 0;
    private $cache   = [];   // size (string) → id_attribute (int)
    private $langs   = [];

    public function __construct()
    {
        $this->langs = Language::getLanguages(true);
    }

    /**
     * Ensures AttributeGroup "Dydis" exists, then finds or creates each distinct
     * size from Bikes/E-Bikes staging rows.
     * Returns ['group_id' => N, 'found' => N, 'created' => N].
     */
    public function warmUp(): array
    {
        $this->groupId = $this->ensureGroup();

        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `size` FROM `' . _DB_PREFIX_ . "mybike_product`
             WHERE `section` IN ('Bikes', 'E-Bikes')
               AND `size` != ''
               AND `avail_status` != 'deleted'"
        );

        $found   = 0;
        $created = 0;

        foreach ($rows as $row) {
            $size = trim($row['size']);
            if ($size === '') {
                continue;
            }

            $existingId = $this->findAttributeId($size);

            if ($existingId > 0) {
                $this->cache[$size] = $existingId;
                $found++;
            } else {
                $id = $this->createAttribute($size);
                if ($id > 0) {
                    $this->cache[$size] = $id;
                    $created++;
                }
            }
        }

        return ['group_id' => $this->groupId, 'found' => $found, 'created' => $created];
    }

    /**
     * Returns id_attribute for a size value. 0 if not in cache.
     */
    public function getAttributeId(string $size): int
    {
        return $this->cache[trim($size)] ?? 0;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    private function ensureGroup(): int
    {
        $id = (int)Db::getInstance()->getValue(
            'SELECT `id_attribute_group` FROM `' . _DB_PREFIX_ . 'attribute_group_lang`
             WHERE `name` = \'' . pSQL(self::GROUP_NAME) . '\' LIMIT 1'
        );

        if ($id > 0) {
            return $id;
        }

        $ag                 = new AttributeGroup();
        $ag->group_type     = 'select';
        $ag->is_color_group = 0;
        $ag->position       = 0;

        foreach ($this->langs as $lang) {
            $lid                   = $lang['id_lang'];
            $ag->name[$lid]        = self::GROUP_NAME;
            $ag->public_name[$lid] = self::GROUP_NAME;
        }

        return $ag->add() ? (int)$ag->id : 0;
    }

    private function findAttributeId(string $size): int
    {
        return (int)Db::getInstance()->getValue(
            'SELECT al.`id_attribute`
             FROM `' . _DB_PREFIX_ . 'attribute_lang` al
             JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = al.`id_attribute`
             WHERE a.`id_attribute_group` = ' . (int)$this->groupId . '
               AND al.`name` = \'' . pSQL($size) . '\'
             LIMIT 1'
        );
    }

    private function createAttribute(string $size): int
    {
        $attr                    = new Attribute();
        $attr->id_attribute_group = $this->groupId;
        $attr->position          = 0;

        foreach ($this->langs as $lang) {
            $attr->name[$lang['id_lang']] = $size;
        }

        return $attr->add() ? (int)$attr->id : 0;
    }
}
