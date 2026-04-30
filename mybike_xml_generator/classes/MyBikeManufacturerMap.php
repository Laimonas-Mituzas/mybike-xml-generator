<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeManufacturerMap
{
    private $cache = [];   // brand → id_manufacturer
    private $langs = [];

    public function __construct()
    {
        $this->langs = Language::getLanguages(true);
    }

    /**
     * Loads all distinct brands from staging, finds or creates ps_manufacturer entries.
     * Returns ['found' => N, 'created' => N].
     */
    public function warmUp(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `brand` FROM `' . _DB_PREFIX_ . "mybike_product`
             WHERE `brand` != '' AND `avail_status` != 'deleted'"
        );

        $found   = 0;
        $created = 0;

        foreach ($rows as $row) {
            $brand = trim($row['brand']);
            if ($brand === '') {
                continue;
            }

            $existingId = (int)Db::getInstance()->getValue(
                'SELECT `id_manufacturer` FROM `' . _DB_PREFIX_ . 'manufacturer`
                 WHERE `name` = \'' . pSQL($brand) . '\''
            );

            if ($existingId > 0) {
                $this->cache[$brand] = $existingId;
                $found++;
            } else {
                $id = $this->create($brand);
                if ($id > 0) {
                    $this->cache[$brand] = $id;
                    $created++;
                }
            }
        }

        return ['found' => $found, 'created' => $created];
    }

    /**
     * Returns id_manufacturer for a brand. 0 if brand not in cache.
     */
    public function getId(string $brand): int
    {
        return $this->cache[trim($brand)] ?? 0;
    }

    private function create(string $brand): int
    {
        $m         = new Manufacturer();
        $m->name   = $brand;
        $m->active = 1;

        foreach ($this->langs as $lang) {
            $lid = $lang['id_lang'];
            $m->meta_title[$lid]        = '';
            $m->meta_description[$lid]  = '';
            $m->description[$lid]       = '';
            $m->short_description[$lid] = '';
        }

        return $m->add() ? (int)$m->id : 0;
    }
}
