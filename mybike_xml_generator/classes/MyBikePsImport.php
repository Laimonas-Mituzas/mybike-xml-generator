<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikePsImport
{
    private $logger;
    private $manufacturerMap;
    private $attributeMap;
    private $priceCalc;
    private $langs;
    private $defaultLangId;
    private $categoryCache  = [];  // mybike_category_id → ps_id_category (0 = unmapped)
    private $newProductMeta = [];  // id_product → representative row (for image sync)
    private $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'images' => 0, 'warnings' => []];

    const BIKE_SECTIONS  = ['Bikes', 'E-Bikes'];
    const PARTS_SECTIONS = ['Parts', 'Accessories'];

    public function __construct(MyBikeLogger $logger)
    {
        $this->logger           = $logger;
        $this->manufacturerMap  = new MyBikeManufacturerMap();
        $this->attributeMap     = new MyBikeAttributeMap();
        $this->priceCalc        = MyBikePriceCalc::fromConfig();
        $this->langs            = Language::getLanguages(true);
        $this->defaultLangId    = (int)Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * Imports a single product (or its full group for Bikes/E-Bikes) from staging.
     * Pass mybike_id = 0 to use the first available staging row.
     * Returns the standard stats array plus 'mybike_id', 'section', 'name',
     * 'group_size', 'ps_id_product'.
     */
    public function runSingle(int $mybike_id = 0): array
    {
        $start = microtime(true);

        if ($mybike_id <= 0) {
            $first = Db::getInstance()->getRow(
                "SELECT `mybike_id` FROM `" . _DB_PREFIX_ . "mybike_product`
                 WHERE `avail_status` != 'deleted'
                 ORDER BY `mybike_id` LIMIT 1"
            );
            if (!$first) {
                throw new Exception('Staging lentelė tuščia');
            }
            $mybike_id = (int)$first['mybike_id'];
        }

        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'mybike_product`
             WHERE `mybike_id` = ' . $mybike_id
        );

        if (!$row) {
            throw new Exception('mybike_id=' . $mybike_id . ' nerastas staging lentelėje');
        }

        $this->logger->info('Single test: mybike_id=' . $mybike_id . ' section=' . $row['section']);

        $mfResult   = $this->manufacturerMap->warmUp();
        $attrResult = $this->attributeMap->warmUp();
        $this->logger->info('ManufacturerMap: found=' . $mfResult['found'] . ' created=' . $mfResult['created']);
        $this->logger->info('AttributeMap: group_id=' . $attrResult['group_id'] . ' found=' . $attrResult['found'] . ' created=' . $attrResult['created']);

        $section = $row['section'];

        if (in_array($section, self::BIKE_SECTIONS, true)) {
            // Load entire color-group so combinations can be determined correctly
            $groupRows = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . "mybike_product`
                 WHERE `brand` = '" . pSQL($row['brand']) . "'
                   AND `model` = '" . pSQL($row['model']) . "'
                   AND `color` = '" . pSQL($row['color']) . "'
                   AND `section` = '" . pSQL($section) . "'
                   AND `avail_status` != 'deleted'
                 ORDER BY `mybike_id`"
            );
            $this->stats['group_size'] = count($groupRows);
            $this->processGroup($groupRows);
        } else {
            $this->stats['group_size'] = 1;
            $this->processRow($row);
        }

        $this->syncImages();

        $duration                     = (int)round(microtime(true) - $start);
        $this->stats['duration']      = $duration;
        $this->stats['mybike_id']     = $mybike_id;
        $this->stats['section']       = $section;
        $this->stats['name']          = $this->buildName($row);
        $this->stats['warnings']      = $this->stats['warnings'] ?? [];

        // Re-fetch to show assigned PS IDs
        $finalRow = Db::getInstance()->getRow(
            'SELECT `ps_id_product` FROM `' . _DB_PREFIX_ . 'mybike_product`
             WHERE `mybike_id` = ' . $mybike_id
        );
        $this->stats['ps_id_product'] = $finalRow ? (int)$finalRow['ps_id_product'] : 0;

        $this->logger->info(
            'Single test done: mybike_id=' . $mybike_id
            . ' ps_id_product=' . $this->stats['ps_id_product']
            . ' imported=' . $this->stats['imported']
            . ' updated=' . $this->stats['updated']
            . ' skipped=' . $this->stats['skipped']
            . ' ' . $duration . 's'
        );

        return $this->stats;
    }

    /**
     * Runs full PS import from staging table.
     * Returns ['imported', 'updated', 'skipped', 'warnings', 'duration'].
     */
    public function run(): array
    {
        $start = microtime(true);
        $this->logger->info('PS import started');

        $warnings = $this->preCheck();
        foreach ($warnings as $w) {
            $this->logger->info('WARNING: ' . $w);
        }
        $this->stats['warnings'] = $warnings;

        $mfResult   = $this->manufacturerMap->warmUp();
        $attrResult = $this->attributeMap->warmUp();
        $this->logger->info('ManufacturerMap: found=' . $mfResult['found'] . ' created=' . $mfResult['created']);
        $this->logger->info('AttributeMap: group_id=' . $attrResult['group_id'] . ' found=' . $attrResult['found'] . ' created=' . $attrResult['created']);

        foreach (array_merge(self::BIKE_SECTIONS, self::PARTS_SECTIONS) as $section) {
            $this->importSection($section);
        }

        $this->syncImages();

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info(
            'PS import done: imported=' . $this->stats['imported']
            . ' updated=' . $this->stats['updated']
            . ' skipped=' . $this->stats['skipped']
            . ' images=' . $this->stats['images']
            . ' ' . $duration . 's'
        );

        $this->stats['duration'] = $duration;
        return $this->stats;
    }

    // -------------------------------------------------------------------
    // PRE-CHECK
    // -------------------------------------------------------------------

    private function preCheck(): array
    {
        $warnings = [];

        // Find enabled sections/categories from v1 XML filter that have no category mapping
        $rows = Db::getInstance()->executeS(
            'SELECT xc.`section`, xc.`title`
             FROM `' . _DB_PREFIX_ . 'mybike_xml_category` xc
             WHERE xc.`enabled` = 1
               AND NOT EXISTS (
                   SELECT 1 FROM `' . _DB_PREFIX_ . 'mybike_category_map` cm
                   WHERE cm.`mybike_category_id` = xc.`id_category`
                     AND cm.`ps_id_category` IS NOT NULL
               )'
        );

        foreach ($rows as $row) {
            $warnings[] = 'No PS category mapped for: [' . $row['section'] . '] ' . $row['title'] . ' — products will import without category';
        }

        return $warnings;
    }

    // -------------------------------------------------------------------
    // SECTION IMPORT
    // -------------------------------------------------------------------

    private function importSection(string $section): void
    {
        $rows = $this->fetchSectionRows($section);
        $this->logger->info('Section "' . $section . '": ' . count($rows) . ' staging rows');

        if (empty($rows)) {
            return;
        }

        if (in_array($section, self::BIKE_SECTIONS, true)) {
            $groups = $this->groupByBrandModelColor($rows);
            foreach ($groups as $group) {
                $this->processGroup($group);
            }
        } else {
            foreach ($rows as $row) {
                $this->processRow($row);
            }
        }
    }

    private function fetchSectionRows(string $section): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . "mybike_product`
             WHERE `section` = '" . pSQL($section) . "'
               AND `avail_status` != 'deleted'
             ORDER BY `brand`, `model`, `color`, `mybike_id`"
        );
    }

    private function groupByBrandModelColor(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['brand'] . '|' . $row['model'] . '|' . $row['color'];
            $groups[$key][] = $row;
        }
        return array_values($groups);
    }

    // -------------------------------------------------------------------
    // GROUP PROCESSING (Bikes / E-Bikes)
    // -------------------------------------------------------------------

    private function processGroup(array $rows): void
    {
        $result   = $this->determineType($rows);
        $type     = $result['type'];
        $rep      = $result['representative'];
        $combos   = $result['combinations'];

        if ($rep === null) {
            $this->stats['skipped']++;
            return;
        }

        // Existing ps_id_product: take from any non-null row in the group
        $existingProductId = 0;
        foreach ($rows as $r) {
            if (!empty($r['ps_id_product'])) {
                $existingProductId = (int)$r['ps_id_product'];
                break;
            }
        }

        $idProduct = $this->createOrUpdateProduct($rep, $existingProductId);
        if ($idProduct <= 0) {
            $this->stats['skipped']++;
            return;
        }

        if ($existingProductId === 0) {
            $this->newProductMeta[$idProduct] = $rep;
        }

        // Update ps_id_product for all rows in group
        $mybike_ids = array_column($rows, 'mybike_id');
        $this->updateStagingProductId($mybike_ids, $idProduct);

        if ($type === 'standard') {
            StockAvailable::setQuantity($idProduct, 0, (int)$rep['avail_quantity']);
            $this->updateStagingAttr((int)$rep['mybike_id'], 0);
        } else {
            $product = new Product($idProduct, false);
            foreach ($combos as $i => $comboRow) {
                $isDefault  = ($i === 0);
                $idAttr     = $this->saveCombo($product, $comboRow, $isDefault);
                if ($idAttr > 0) {
                    $this->updateStagingAttr((int)$comboRow['mybike_id'], $idAttr);
                }
            }
        }
    }

    // -------------------------------------------------------------------
    // ROW PROCESSING (Parts / Accessories)
    // -------------------------------------------------------------------

    private function processRow(array $row): void
    {
        $existingProductId = (int)($row['ps_id_product'] ?? 0);
        $idProduct         = $this->createOrUpdateProduct($row, $existingProductId);

        if ($idProduct <= 0) {
            $this->stats['skipped']++;
            return;
        }

        if ($existingProductId === 0) {
            $this->newProductMeta[$idProduct] = $row;
        }

        StockAvailable::setQuantity($idProduct, 0, (int)$row['avail_quantity']);
        $this->updateStagingProductId([(int)$row['mybike_id']], $idProduct);
        $this->updateStagingAttr((int)$row['mybike_id'], 0);
    }

    // -------------------------------------------------------------------
    // TYPE DETERMINATION
    // -------------------------------------------------------------------

    private function determineType(array $rows): array
    {
        // Separate rows with and without size
        $sizeMap    = [];   // size → best row (max qty, tie → min mybike_id)
        $noSizeRows = [];

        foreach ($rows as $row) {
            $size = $row['size'];
            if ($size === '') {
                $noSizeRows[] = $row;
            } elseif (!isset($sizeMap[$size])) {
                $sizeMap[$size] = $row;
            } else {
                $existing = $sizeMap[$size];
                if ((int)$row['avail_quantity'] > (int)$existing['avail_quantity']
                    || ((int)$row['avail_quantity'] === (int)$existing['avail_quantity']
                        && (int)$row['mybike_id'] < (int)$existing['mybike_id'])
                ) {
                    $this->logger->info('Dup size "' . $size . '": skip mybike_id=' . $existing['mybike_id'] . ', keep=' . $row['mybike_id']);
                    $sizeMap[$size] = $row;
                } else {
                    $this->logger->info('Dup size "' . $size . '": skip mybike_id=' . $row['mybike_id']);
                }
            }
        }

        $uniqueSizes   = count($sizeMap);
        $totalVariants = count($rows);

        // unique_sizes == 0 → TYPE_STANDARD (keep max qty from no-size rows)
        if ($uniqueSizes === 0) {
            if (empty($noSizeRows)) {
                return ['type' => 'standard', 'representative' => null, 'combinations' => []];
            }
            $rep = $noSizeRows[0];
            foreach ($noSizeRows as $r) {
                if ((int)$r['avail_quantity'] > (int)$rep['avail_quantity']
                    || ((int)$r['avail_quantity'] === (int)$rep['avail_quantity']
                        && (int)$r['mybike_id'] < (int)$rep['mybike_id'])
                ) {
                    $rep = $r;
                }
            }
            foreach ($noSizeRows as $r) {
                if ((int)$r['mybike_id'] !== (int)$rep['mybike_id']) {
                    $this->logger->info('Skipped mybike_id=' . $r['mybike_id'] . ' (no size differentiator, group=' . $rep['brand'] . ' ' . $rep['model'] . ')');
                }
            }
            return ['type' => 'standard', 'representative' => $rep, 'combinations' => []];
        }

        // unique_sizes == 1 AND single variant → TYPE_STANDARD
        if ($uniqueSizes === 1 && $totalVariants === 1) {
            $rep = reset($sizeMap);
            return ['type' => 'standard', 'representative' => $rep, 'combinations' => []];
        }

        // TYPE_COMBINATIONS — natural sort sizes
        $sizes = array_keys($sizeMap);
        usort($sizes, 'strnatcasecmp');

        $combos = [];
        foreach ($sizes as $s) {
            $combos[] = $sizeMap[$s];
        }

        return ['type' => 'combinations', 'representative' => $combos[0], 'combinations' => $combos];
    }

    // -------------------------------------------------------------------
    // PRODUCT CREATE / UPDATE
    // -------------------------------------------------------------------

    private function createOrUpdateProduct(array $repRow, int $existingProductId): int
    {
        if ($existingProductId > 0) {
            $product = new Product($existingProductId, true);
            if (!Validate::isLoadedObject($product)) {
                $existingProductId = 0;
                $product = new Product();
            }
        } else {
            $product = new Product();
        }

        $name       = $this->buildName($repRow);
        $categoryId = $this->getCategoryId((int)$repRow['category_id']);

        $product->name              = $this->getMultilang($name);
        $product->description       = $this->getMultilang((string)($repRow['description'] ?? ''));
        $product->link_rewrite      = $this->getMultilang(Tools::link_rewrite($name));
        $product->reference         = (string)$repRow['manufacturer_id'];
        $product->mpn               = (string)$repRow['standard_item_id'];
        $product->id_manufacturer   = $this->manufacturerMap->getId((string)$repRow['brand']);
        $product->price             = $this->priceCalc->calc($repRow);
        $product->wholesale_price   = $this->priceCalc->wholesale($repRow);
        $product->active              = 1;
        $product->condition           = 'new';
        $product->visibility          = 'both';
        $product->id_tax_rules_group  = (int)Configuration::get('MYBIKE_IMPORT_TAX_RULES_ID');

        if ($existingProductId === 0) {
            $product->product_type = 'standard'; // may be upgraded to 'combinations' by addAttribute()
        }

        if ($categoryId > 0) {
            $product->id_category_default = $categoryId;
        }

        if ($existingProductId > 0) {
            if (!$product->update()) {
                $this->logger->error('Product update failed for mybike_id=' . $repRow['mybike_id']);
                return 0;
            }
            $this->stats['updated']++;
        } else {
            if (!$product->add()) {
                $this->logger->error('Product add failed for mybike_id=' . $repRow['mybike_id']);
                return 0;
            }
            $this->stats['imported']++;
        }

        $idProduct = (int)$product->id;

        if ($categoryId > 0) {
            $product->addToCategories([$categoryId]);
        }

        // Mark imported_at on first create
        if ($existingProductId === 0) {
            Db::getInstance()->update(
                'mybike_product',
                ['imported_at' => date('Y-m-d H:i:s')],
                '`mybike_id` = ' . (int)$repRow['mybike_id']
            );
        }

        return $idProduct;
    }

    // -------------------------------------------------------------------
    // COMBINATION CREATE / UPDATE
    // -------------------------------------------------------------------

    private function saveCombo(Product $product, array $row, bool $isDefault): int
    {
        $idAttrValue   = $this->attributeMap->getAttributeId((string)$row['size']);
        $existingAttr  = (int)($row['ps_id_product_attr'] ?? 0);
        $availDate     = $row['avail_date'] ?: null;

        if ($idAttrValue <= 0) {
            $this->logger->error('No attribute id for size "' . $row['size'] . '" mybike_id=' . $row['mybike_id']);
            return 0;
        }

        if ($existingAttr > 0) {
            // Update existing combination
            $combination = new Combination($existingAttr);
            if (!Validate::isLoadedObject($combination)) {
                $existingAttr = 0;
            } else {
                $combination->reference     = (string)$row['manufacturer_id'];
                $combination->mpn           = (string)$row['standard_item_id'];
                $combination->price         = 0;
                $combination->default_on    = $isDefault ? 1 : 0;
                $combination->available_date = $availDate;
                $combination->update();
                StockAvailable::setQuantity((int)$product->id, $existingAttr, (int)$row['avail_quantity']);
                return $existingAttr;
            }
        }

        // Create new combination
        $idCombination = $product->addAttribute(
            0,                              // price delta
            0,                              // weight
            0,                              // unit_impact
            0,                              // ecotax
            [],                             // id_images
            (string)$row['manufacturer_id'],// reference
            null,                           // ean13
            $isDefault ? 1 : 0,             // default_on
            null,                           // location
            null,                           // upc
            1,                              // minimal_quantity
            [],                             // id_shop_list
            $availDate,                     // available_date
            0,                              // quantity (set via StockAvailable)
            '',                             // isbn
            null,                           // low_stock_threshold
            false,                          // low_stock_alert
            (string)$row['standard_item_id'] // mpn
        );

        if ($idCombination <= 0) {
            $this->logger->error('addAttribute failed for mybike_id=' . $row['mybike_id']);
            return 0;
        }

        $combo = new Combination($idCombination);
        $combo->setAttributes([$idAttrValue]);
        StockAvailable::setQuantity((int)$product->id, $idCombination, (int)$row['avail_quantity']);

        return $idCombination;
    }

    // -------------------------------------------------------------------
    // STAGING UPDATE
    // -------------------------------------------------------------------

    private function updateStagingProductId(array $mybike_ids, int $idProduct): void
    {
        $ids = implode(',', array_map('intval', $mybike_ids));
        Db::getInstance()->update(
            'mybike_product',
            ['ps_id_product' => $idProduct],
            '`mybike_id` IN (' . $ids . ')'
        );
    }

    private function updateStagingAttr(int $mybikeId, int $idAttr): void
    {
        Db::getInstance()->update(
            'mybike_product',
            ['ps_id_product_attr' => $idAttr],
            '`mybike_id` = ' . $mybikeId
        );
    }

    // -------------------------------------------------------------------
    // IMAGE SYNC (new products only)
    // -------------------------------------------------------------------

    private function syncImages(): void
    {
        if (empty($this->newProductMeta)) {
            return;
        }

        $this->logger->info('Image sync: ' . count($this->newProductMeta) . ' new products');

        foreach ($this->newProductMeta as $idProduct => $repRow) {
            $images = json_decode((string)($repRow['images'] ?? ''), true);
            if (empty($images) || !is_array($images)) {
                continue;
            }

            $position = 0;
            foreach ($images as $img) {
                $url = (string)($img['url'] ?? '');
                if ($url === '') {
                    continue;
                }

                $position++;
                $tmpFile = _PS_TMP_IMG_DIR_ . 'mybike_' . $idProduct . '_' . $position . '.jpg';

                if (!$this->downloadImage($url, $tmpFile)) {
                    $this->logger->error('Image download failed: ' . $url . ' (product ' . $idProduct . ')');
                    continue;
                }

                $image             = new Image();
                $image->id_product = $idProduct;
                $image->position   = $position;
                $image->cover      = ($position === 1) ? 1 : 0;

                if (!$image->add()) {
                    @unlink($tmpFile);
                    $this->logger->error('Image::add() failed for product ' . $idProduct);
                    continue;
                }

                // Create directory and move file to final location
                $imgBasePath = _PS_PROD_IMG_DIR_ . $image->getImgPath();
                $imgDir      = dirname($imgBasePath);
                if (!is_dir($imgDir)) {
                    mkdir($imgDir, 0755, true);
                }

                $finalPath = $imgBasePath . '.jpg';
                rename($tmpFile, $finalPath);

                // Generate thumbnails for all product image types
                foreach (ImageType::getImagesTypes('products') as $imageType) {
                    $dst = $imgBasePath . '-' . $imageType['name'] . '.jpg';
                    ImageManager::resize($finalPath, $dst, (int)$imageType['width'], (int)$imageType['height']);
                }

                $this->stats['images']++;
            }
        }

        $this->logger->info('Image sync done: ' . $this->stats['images'] . ' images created');
    }

    private function downloadImage(string $url, string $dest): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $data  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno || !$data) {
            return false;
        }

        return file_put_contents($dest, $data) !== false;
    }

    // -------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------

    private function buildName(array $row): string
    {
        $parts = [$row['brand'], $row['model']];
        if ($row['color'] !== '') {
            $parts[] = $row['color'];
        }
        return implode(' ', array_filter($parts));
    }

    private function getCategoryId(int $mybikeCategoryId): int
    {
        if (!isset($this->categoryCache[$mybikeCategoryId])) {
            $row = Db::getInstance()->getRow(
                'SELECT `ps_id_category` FROM `' . _DB_PREFIX_ . 'mybike_category_map`
                 WHERE `mybike_category_id` = ' . (int)$mybikeCategoryId
            );
            $this->categoryCache[$mybikeCategoryId] = $row ? (int)$row['ps_id_category'] : 0;
        }
        return $this->categoryCache[$mybikeCategoryId];
    }

    private function getMultilang(string $value): array
    {
        $result = [];
        foreach ($this->langs as $lang) {
            $result[$lang['id_lang']] = $value;
        }
        return $result;
    }
}
