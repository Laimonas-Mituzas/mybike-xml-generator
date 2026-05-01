<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Generates products_full.xml from ps_mybike_product staging table.
 * One <product> per unique brand+model+color group (representative row).
 * Same dedup logic as MyBikeCombinationsXml: max qty wins, tie → min mybike_id.
 */
class MyBikeFullDbXml
{
    private $outputFile;
    private $logger;

    const SECTIONS = ['Bikes', 'E-Bikes', 'Parts', 'Accessories'];

    public function __construct(string $outputFile, MyBikeLogger $logger)
    {
        $this->outputFile = $outputFile;
        $this->logger     = $logger;
    }

    public function build(): int
    {
        $start = microtime(true);
        $this->logger->info('Full XML build started');

        $tmp = $this->outputFile . '.tmp';
        $xw  = new XMLWriter();
        $xw->openURI($tmp);
        $xw->startDocument('1.0', 'UTF-8');
        $xw->setIndent(false);
        $xw->startElement('products');
        $xw->writeAttribute('generated', date('c'));

        $total = 0;

        foreach (self::SECTIONS as $section) {
            $rows = Db::getInstance()->executeS(
                "SELECT * FROM `" . _DB_PREFIX_ . "mybike_product`
                 WHERE `section` = '" . pSQL($section) . "'
                   AND `avail_status` != 'deleted'
                 ORDER BY `brand`, `model`, `color`, `mybike_id`"
            );

            foreach ($this->groupAndResolve($rows) as $rep) {
                $this->writeProduct($xw, $rep);
                $total++;
            }
        }

        $xw->endElement();
        $xw->flush();
        unset($xw);

        rename($tmp, $this->outputFile);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info('Full XML done: ' . $total . ' products, ' . $duration . 's');

        return $total;
    }

    // -----------------------------------------------------------------------

    private function groupAndResolve(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['brand'] . '|' . $row['model'] . '|' . $row['color'];
            $groups[$key][] = $row;
        }

        $reps = [];
        foreach ($groups as $group) {
            $reps[] = $this->resolveRepresentative($group);
        }
        return $reps;
    }

    private function resolveRepresentative(array $rows): array
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
                return $rows[0];
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
            return $rep;
        }

        $sizes = array_keys($sizeMap);
        usort($sizes, 'strnatcasecmp');
        return $sizeMap[$sizes[0]];
    }

    private function writeProduct(XMLWriter $xw, array $row): void
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
}
