<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Generates products_full.xml AND products_combinations.xml in one DB pass.
 *
 * Both files share the same brand+model+color grouping and dedup logic.
 * Full XML   : one <product> per unique brand+model+color (all sections).
 * Combinations XML : only Bikes/E-Bikes groups with multiple size variants.
 */
class MyBikeProductsXml
{
    private $fullFile;
    private $combinationsFile;
    private $logger;

    const SECTIONS      = ['Bikes', 'E-Bikes', 'Parts', 'Accessories'];
    const BIKE_SECTIONS = ['Bikes', 'E-Bikes'];

    public function __construct(string $fullFile, string $combinationsFile, MyBikeLogger $logger)
    {
        $this->fullFile         = $fullFile;
        $this->combinationsFile = $combinationsFile;
        $this->logger           = $logger;
    }

    public function build(): array
    {
        $start = microtime(true);
        $this->logger->info('Products XML build started (full + combinations)');

        $tmpFull = $this->fullFile . '.tmp';
        $tmpComb = $this->combinationsFile . '.tmp';

        $xwFull = new XMLWriter();
        $xwFull->openURI($tmpFull);
        $xwFull->startDocument('1.0', 'UTF-8');
        $xwFull->setIndent(false);
        $xwFull->startElement('products');
        $xwFull->writeAttribute('generated', date('c'));

        $xwComb = new XMLWriter();
        $xwComb->openURI($tmpComb);
        $xwComb->startDocument('1.0', 'UTF-8');
        $xwComb->setIndent(false);
        $xwComb->startElement('products');
        $xwComb->writeAttribute('generated', date('c'));

        $fullCount = 0;
        $combCount = 0;

        foreach (self::SECTIONS as $section) {
            $rows = Db::getInstance()->executeS(
                "SELECT * FROM `" . _DB_PREFIX_ . "mybike_product`
                 WHERE `section` = '" . pSQL($section) . "'
                   AND `avail_status` != 'deleted'
                 ORDER BY `brand`, `model`, `color`, `mybike_id`"
            );

            $isBikeSection = in_array($section, self::BIKE_SECTIONS, true);

            foreach ($this->groupByBrandModelColor($rows) as $group) {
                $resolved = $this->resolveGroup($group);
                $rep      = $resolved['representative'];
                $variants = $resolved['variants'];
                $type     = $resolved['type'];

                $this->writeFullProduct($xwFull, $rep);
                $fullCount++;

                if ($isBikeSection && $type === 'combinations') {
                    $this->writeCombination($xwComb, $rep, $variants);
                    $combCount++;
                }
            }
        }

        $xwFull->endElement();
        $xwFull->flush();
        unset($xwFull);

        $xwComb->endElement();
        $xwComb->flush();
        unset($xwComb);

        rename($tmpFull, $this->fullFile);
        rename($tmpComb, $this->combinationsFile);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info("Products XML done: full={$fullCount}, combinations={$combCount}, {$duration}s");

        return ['full' => $fullCount, 'combinations' => $combCount, 'duration' => $duration];
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

    private function writeFullProduct(XMLWriter $xw, array $row): void
    {
        $xw->startElement('product');

        $xw->writeElement('id',               (string)$row['mybike_id']);
        $xw->writeElement('standard_item_id', (string)$row['standard_item_id']);
        $xw->writeElement('manufacturer_id',  (string)$row['manufacturer_id']);
        $xw->writeElement('brand',            (string)$row['brand']);
        $xw->writeElement('model',            (string)$row['model']);
        $xw->writeElement('type',             (string)$row['type']);
        $xw->writeElement('section',          (string)$row['section']);
        $xw->writeElement('category',         (string)$row['category']);
        $xw->writeElement('category_id',      (string)$row['category_id']);
        $xw->writeElement('sub_category',     (string)$row['sub_category']);
        $xw->writeElement('price',            (string)$row['price']);
        $xw->writeElement('base_price',       (string)$row['base_price']);
        $xw->writeElement('color',            (string)$row['color']);
        $xw->writeElement('featured',         (string)$row['featured']);

        $xw->startElement('description');
        $xw->writeCdata((string)($row['description'] ?? ''));
        $xw->endElement();

        $xw->startElement('specs');
        $xw->writeCdata((string)($row['specs'] ?? ''));
        $xw->endElement();

        $xw->startElement('availability');
        $xw->writeElement('status',   (string)$row['avail_status']);
        $xw->writeElement('quantity', (string)$row['avail_quantity']);
        $xw->writeElement('date',     (string)($row['avail_date'] ?? ''));
        $xw->endElement();

        $xw->writeElement('ps_id_product',      (string)($row['ps_id_product'] ?? ''));
        $xw->writeElement('ps_id_product_attr', (string)($row['ps_id_product_attr'] ?? ''));

        $images = json_decode((string)($row['images'] ?? ''), true);
        if (!empty($images) && is_array($images)) {
            $xw->startElement('images');
            foreach ($images as $img) {
                $xw->startElement('image');
                $xw->writeAttribute('url', (string)($img['url'] ?? ''));
                $xw->endElement();
            }
            $xw->endElement();
        }

        $xw->endElement(); // product
    }

    private function writeCombination(XMLWriter $xw, array $rep, array $variants): void
    {
        $prices       = array_unique(array_column($variants, 'price'));
        $basePrices   = array_unique(array_column($variants, 'base_price'));
        $uniformPrice = count($prices) === 1 && count($basePrices) === 1;

        $xw->startElement('product');
        $xw->writeAttribute('type',          'combinations');
        $xw->writeAttribute('ps_id_product', (string)($rep['ps_id_product'] ?? ''));

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
    }
}
