<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Generates products_combinations.xml from ps_mybike_product staging table.
 *
 * Only Bikes/E-Bikes with multiple size variants (type="combinations").
 * Single-size and no-size groups (type="standard") are skipped.
 *
 * Price handling:
 *   - Uniform across all variants  → <price>/<base_price> at product level
 *   - Differs between any variants → <price>/<base_price> inside each <variant>
 */
class MyBikeCombinationsXml
{
    private $outputFile;
    private $logger;

    const BIKE_SECTIONS = ['Bikes', 'E-Bikes'];

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

        foreach (self::BIKE_SECTIONS as $section) {
            $rows = Db::getInstance()->executeS(
                "SELECT * FROM `" . _DB_PREFIX_ . "mybike_product`
                 WHERE `section` = '" . pSQL($section) . "'
                   AND `avail_status` != 'deleted'
                 ORDER BY `brand`, `model`, `color`, `mybike_id`"
            );

            foreach ($this->groupByBrandModelColor($rows) as $group) {
                $written = $this->writeGroup($xw, $group);
                if ($written) {
                    $total++;
                }
            }
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

        $sizes = array_keys($sizeMap);
        usort($sizes, 'strnatcasecmp');

        $variants = [];
        foreach ($sizes as $s) {
            $variants[] = $sizeMap[$s];
        }

        $type = count($variants) > 1 ? 'combinations' : 'standard';
        return ['representative' => $variants[0], 'variants' => $variants, 'type' => $type];
    }

    /**
     * Writes a combinations-type group. Returns true if written, false if skipped.
     */
    private function writeGroup(XMLWriter $xw, array $rows): bool
    {
        $resolved = $this->resolveGroup($rows);
        $type     = $resolved['type'];

        // Only write combination products (multiple size variants)
        if ($type !== 'combinations') {
            return false;
        }

        $rep      = $resolved['representative'];
        $variants = $resolved['variants'];

        // Check if all variants share the same price and base_price
        $prices     = array_unique(array_column($variants, 'price'));
        $basePrices = array_unique(array_column($variants, 'base_price'));
        $uniformPrice = count($prices) === 1 && count($basePrices) === 1;

        $xw->startElement('product');
        $xw->writeAttribute('type',          'combinations');
        $xw->writeAttribute('ps_id_product', (string)($rep['ps_id_product'] ?? ''));

        // mybike_id first — representative (first variant by size)
        $xw->writeElement('mybike_id',   (string)$rep['mybike_id']);
        $xw->writeElement('brand',       (string)$rep['brand']);
        $xw->writeElement('model',       (string)$rep['model']);
        $xw->writeElement('color',       (string)$rep['color']);
        $xw->writeElement('section',     (string)$rep['section']);
        $xw->writeElement('category',    (string)$rep['category']);
        $xw->writeElement('category_id', (string)$rep['category_id']);

        if ($uniformPrice) {
            $xw->writeElement('price',      (string)$rep['price']);
            $xw->writeElement('base_price', (string)$rep['base_price']);
        }

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

        $xw->startElement('variants');
        $xw->writeAttribute('count', (string)count($variants));
        foreach ($variants as $i => $v) {
            $xw->startElement('variant');
            $xw->writeAttribute('mybike_id',          (string)$v['mybike_id']);
            $xw->writeAttribute('size',               (string)$v['size']);
            $xw->writeAttribute('default',            $i === 0 ? '1' : '0');
            $xw->writeAttribute('ps_id_product_attr', (string)($v['ps_id_product_attr'] ?? ''));
            $xw->writeElement('manufacturer_id',  (string)$v['manufacturer_id']);
            $xw->writeElement('standard_item_id', (string)$v['standard_item_id']);
            if (!$uniformPrice) {
                $xw->writeElement('price',      (string)$v['price']);
                $xw->writeElement('base_price', (string)$v['base_price']);
            }
            $xw->startElement('availability');
            $xw->writeElement('status',   (string)$v['avail_status']);
            $xw->writeElement('quantity', (string)$v['avail_quantity']);
            $xw->writeElement('date',     (string)($v['avail_date'] ?? ''));
            $xw->endElement();
            $xw->endElement(); // variant
        }
        $xw->endElement(); // variants

        $xw->endElement(); // product

        return true;
    }
}
