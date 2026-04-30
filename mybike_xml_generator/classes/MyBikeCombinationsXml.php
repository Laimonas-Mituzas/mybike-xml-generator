<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Generates products_combinations.xml from ps_mybike_product staging table.
 *
 * Bikes/E-Bikes: GROUP BY brand+model+color → <product type="combinations">
 *   with <variants> sorted by size (natural). Duplicate sizes deduplicated
 *   same way as MyBikePsImport (max qty wins, tie → min mybike_id).
 *   Single-size or no-size groups → type="standard".
 *
 * Parts/Accessories: one flat <product type="standard"> per row.
 */
class MyBikeCombinationsXml
{
    private $outputFile;
    private $logger;

    const BIKE_SECTIONS  = ['Bikes', 'E-Bikes'];
    const PARTS_SECTIONS = ['Parts', 'Accessories'];

    public function __construct(string $outputFile, MyBikeLogger $logger)
    {
        $this->outputFile = $outputFile;
        $this->logger     = $logger;
    }

    public function build(): int
    {
        $start = microtime(true);
        $this->logger->info('Combinations XML build started');

        $tmp = $this->outputFile . '.tmp';
        $xw  = new XMLWriter();
        $xw->openURI($tmp);
        $xw->startDocument('1.0', 'UTF-8');
        $xw->setIndent(false);
        $xw->startElement('products');
        $xw->writeAttribute('generated', date('c'));

        $total = 0;

        // --- Bikes / E-Bikes: grouped ---
        foreach (self::BIKE_SECTIONS as $section) {
            $rows = Db::getInstance()->executeS(
                "SELECT * FROM `" . _DB_PREFIX_ . "mybike_product`
                 WHERE `section` = '" . pSQL($section) . "'
                   AND `avail_status` != 'deleted'
                 ORDER BY `brand`, `model`, `color`, `mybike_id`"
            );

            foreach ($this->groupByBrandModelColor($rows) as $group) {
                $this->writeGroup($xw, $group);
                $total++;
            }
        }

        // --- Parts / Accessories: flat ---
        foreach (self::PARTS_SECTIONS as $section) {
            $offset = 0;
            $limit  = 500;
            do {
                $rows = Db::getInstance()->executeS(
                    "SELECT * FROM `" . _DB_PREFIX_ . "mybike_product`
                     WHERE `section` = '" . pSQL($section) . "'
                       AND `avail_status` != 'deleted'
                     ORDER BY `mybike_id`
                     LIMIT " . $limit . " OFFSET " . $offset
                );
                foreach ($rows as $row) {
                    $this->writeStandard($xw, $row);
                    $total++;
                }
                $offset += $limit;
            } while (count($rows) === $limit);
        }

        $xw->endElement();
        $xw->flush();
        unset($xw);

        rename($tmp, $this->outputFile);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info('Combinations XML done: ' . $total . ' products, ' . $duration . 's');

        return $total;
    }

    // -----------------------------------------------------------------------

    private function groupByBrandModelColor(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['brand'] . '|' . $row['model'] . '|' . $row['color'];
            $groups[$key][] = $row;
        }
        return array_values($groups);
    }

    /**
     * Resolves variants for a color-group:
     * - Deduplicates same-size rows (max qty wins, tie → min mybike_id)
     * - Sorts by size (natural)
     * - No-size rows: kept only if there are no sized rows
     * Returns ['representative' => row, 'variants' => [row,...], 'type' => 'standard'|'combinations']
     */
    private function resolveGroup(array $rows): array
    {
        $sizeMap    = [];
        $noSizeRows = [];

        foreach ($rows as $row) {
            $size = (string)$row['size'];
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
                    $sizeMap[$size] = $row;
                }
            }
        }

        if (empty($sizeMap)) {
            // All rows have no size → pick best as representative, rest discarded
            if (empty($noSizeRows)) {
                return ['representative' => $rows[0], 'variants' => [], 'type' => 'standard'];
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
            return ['representative' => $rep, 'variants' => [], 'type' => 'standard'];
        }

        // Sort sizes naturally
        $sizes = array_keys($sizeMap);
        usort($sizes, 'strnatcasecmp');

        $variants = [];
        foreach ($sizes as $s) {
            $variants[] = $sizeMap[$s];
        }

        $type = count($variants) > 1 ? 'combinations' : 'standard';
        return ['representative' => $variants[0], 'variants' => $variants, 'type' => $type];
    }

    private function writeGroup(XMLWriter $xw, array $rows): void
    {
        $resolved = $this->resolveGroup($rows);
        $rep      = $resolved['representative'];
        $variants = $resolved['variants'];
        $type     = $resolved['type'];

        $xw->startElement('product');
        $xw->writeAttribute('type',          $type);
        $xw->writeAttribute('ps_id_product', (string)($rep['ps_id_product'] ?? ''));

        $xw->writeElement('brand',      (string)$rep['brand']);
        $xw->writeElement('model',      (string)$rep['model']);
        $xw->writeElement('color',      (string)$rep['color']);
        $xw->writeElement('section',    (string)$rep['section']);
        $xw->writeElement('category',   (string)$rep['category']);
        $xw->writeElement('category_id',(string)$rep['category_id']);
        $xw->writeElement('price',      (string)$rep['price']);
        $xw->writeElement('base_price', (string)$rep['base_price']);

        $xw->startElement('description');
        $xw->writeCdata((string)($rep['description'] ?? ''));
        $xw->endElement();

        $images = json_decode((string)($rep['images'] ?? ''), true);
        if (!empty($images) && is_array($images)) {
            $xw->startElement('images');
            foreach ($images as $img) {
                $xw->startElement('image');
                $xw->writeAttribute('url', (string)($img['url'] ?? ''));
                $xw->endElement();
            }
            $xw->endElement();
        }

        if ($type === 'combinations') {
            $xw->startElement('variants');
            $xw->writeAttribute('count', (string)count($variants));
            foreach ($variants as $i => $v) {
                $xw->startElement('variant');
                $xw->writeAttribute('mybike_id',         (string)$v['mybike_id']);
                $xw->writeAttribute('size',              (string)$v['size']);
                $xw->writeAttribute('default',           $i === 0 ? '1' : '0');
                $xw->writeAttribute('ps_id_product_attr',(string)($v['ps_id_product_attr'] ?? ''));
                $xw->writeElement('manufacturer_id', (string)$v['manufacturer_id']);
                $xw->writeElement('standard_item_id',(string)$v['standard_item_id']);
                $xw->startElement('availability');
                $xw->writeElement('status',   (string)$v['avail_status']);
                $xw->writeElement('quantity', (string)$v['avail_quantity']);
                $xw->writeElement('date',     (string)($v['avail_date'] ?? ''));
                $xw->endElement();
                $xw->endElement(); // variant
            }
            $xw->endElement(); // variants
        } else {
            // Standard — single representative
            $xw->writeElement('mybike_id',        (string)$rep['mybike_id']);
            $xw->writeElement('manufacturer_id',  (string)$rep['manufacturer_id']);
            $xw->writeElement('standard_item_id', (string)$rep['standard_item_id']);
            $xw->writeElement('size',             (string)$rep['size']);
            $xw->writeElement('ps_id_product_attr',(string)($rep['ps_id_product_attr'] ?? ''));
            $xw->startElement('availability');
            $xw->writeElement('status',   (string)$rep['avail_status']);
            $xw->writeElement('quantity', (string)$rep['avail_quantity']);
            $xw->writeElement('date',     (string)($rep['avail_date'] ?? ''));
            $xw->endElement();
        }

        $xw->endElement(); // product
    }

    private function writeStandard(XMLWriter $xw, array $row): void
    {
        $xw->startElement('product');
        $xw->writeAttribute('type',           'standard');
        $xw->writeAttribute('mybike_id',      (string)$row['mybike_id']);
        $xw->writeAttribute('ps_id_product',  (string)($row['ps_id_product'] ?? ''));

        $xw->writeElement('brand',            (string)$row['brand']);
        $xw->writeElement('model',            (string)$row['model']);
        $xw->writeElement('color',            (string)$row['color']);
        $xw->writeElement('section',          (string)$row['section']);
        $xw->writeElement('category',         (string)$row['category']);
        $xw->writeElement('category_id',      (string)$row['category_id']);
        $xw->writeElement('sub_category',     (string)$row['sub_category']);
        $xw->writeElement('price',            (string)$row['price']);
        $xw->writeElement('base_price',       (string)$row['base_price']);
        $xw->writeElement('manufacturer_id',  (string)$row['manufacturer_id']);
        $xw->writeElement('standard_item_id', (string)$row['standard_item_id']);
        $xw->writeElement('ps_id_product_attr',(string)($row['ps_id_product_attr'] ?? ''));

        $xw->startElement('availability');
        $xw->writeElement('status',   (string)$row['avail_status']);
        $xw->writeElement('quantity', (string)$row['avail_quantity']);
        $xw->writeElement('date',     (string)($row['avail_date'] ?? ''));
        $xw->endElement();

        $xw->endElement(); // product
    }
}
