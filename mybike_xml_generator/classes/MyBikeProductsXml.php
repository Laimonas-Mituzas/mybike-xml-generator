<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/MyBikeCategoryManager.php';
require_once dirname(__FILE__) . '/MyBikeSpecsVocab.php';

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
        $xwComb->startElement('COMBINATIONS');
        $xwComb->writeAttribute('generated', date('c'));

        $vocab     = MyBikeSpecsVocab::loadAll();
        $fullCount = 0;
        $combCount = 0;

        $enabledIds = MyBikeCategoryManager::isEmpty() ? [] : MyBikeCategoryManager::getEnabledIds();
        $catFilter  = !empty($enabledIds)
            ? ' AND `category_id` IN (' . implode(',', array_map('intval', $enabledIds)) . ')'
            : '';

        foreach (self::SECTIONS as $section) {
            $rows = Db::getInstance()->executeS(
                "SELECT * FROM `" . _DB_PREFIX_ . "mybike_product`
                 WHERE `section` = '" . pSQL($section) . "'
                   AND `avail_status` != 'deleted'"
                . $catFilter .
                " ORDER BY `brand`, `model`, `color`, `mybike_id`"
            );

            $isBikeSection = in_array($section, self::BIKE_SECTIONS, true);

            foreach ($this->groupByBrandModelColor($rows) as $group) {
                $resolved = $this->resolveGroup($group);
                $rep      = $resolved['representative'];
                $variants = $resolved['variants'];
                $type     = $resolved['type'];

                $this->writeFullProduct($xwFull, $rep, $vocab);
                $fullCount++;

                if ($isBikeSection && $type === 'combinations') {
                    $this->writeCombination($xwComb, $rep, $variants);
                    $combCount += count($variants);
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

    private function productName(array $row): string
    {
        $name = $row['brand'] . ' ' . $row['model'];
        if ($row['color'] !== '') {
            $name .= ' ' . $row['color'];
        }
        return trim($name);
    }

    private function specsToString(string $json, array $vocab): string
    {
        if ($json === '') {
            return '';
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }
        $parts = [];
        foreach ($data as $key => $value) {
            if (!isset($vocab[$key]) || !$vocab[$key]['filterable']) {
                continue;
            }
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }
            if (is_bool($value)) {
                $str = $value ? 'Taip' : 'Ne';
            } else {
                $str = trim((string)$value);
            }
            if ($str === '') {
                continue;
            }
            $parts[] = $vocab[$key]['label_lt'] . ':' . $str;
        }
        return implode('|', $parts);
    }

    private function specsFullToHtml(string $json, array $vocab): string
    {
        if ($json === '') {
            return '';
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }
        $entries = [];
        foreach ($data as $key => $value) {
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }
            if (is_bool($value)) {
                $str = $value ? 'Taip' : 'Ne';
            } else {
                $str = trim((string)$value);
            }
            if ($str === '') {
                continue;
            }
            if (isset($vocab[$key])) {
                if (!$vocab[$key]['show_full']) {
                    continue;
                }
                $entries[] = [
                    'label' => $vocab[$key]['label_lt'],
                    'value' => $str,
                    'sort'  => $vocab[$key]['sort_order'],
                ];
            } else {
                $entries[] = [
                    'label' => $key,
                    'value' => $str,
                    'sort'  => 9999,
                ];
            }
        }
        if (empty($entries)) {
            return '';
        }
        usort($entries, static function ($a, $b) { return $a['sort'] <=> $b['sort']; });
        $html = '<table class="specs-table">';
        foreach ($entries as $e) {
            $html .= '<tr><th>' . htmlspecialchars($e['label'], ENT_XML1) . '</th>'
                   . '<td>' . htmlspecialchars($e['value'], ENT_XML1) . '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function writeFullProduct(XMLWriter $xw, array $row, array $vocab): void
    {
        $xw->startElement('product');

        $xw->writeElement('id',               (string)$row['mybike_id']);
        $xw->writeElement('name',             $this->productName($row));
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

        $xw->writeElement('specs', $this->specsToString((string)($row['specs'] ?? ''), $vocab));

        $specsHtml = $this->specsFullToHtml((string)($row['specs'] ?? ''), $vocab);
        if ($specsHtml !== '') {
            $xw->startElement('specs_full');
            $xw->writeCdata($specsHtml);
            $xw->endElement();
        }

        $xw->writeElement('availability_status', (string)$row['avail_status']);
        $xw->writeElement('availability_date',   (string)($row['avail_date'] ?? ''));
        $xw->writeElement('quantity',            (string)$row['avail_quantity']);

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
        $defaultPrice = (float)$variants[0]['price'];

        foreach ($variants as $i => $v) {
            $images   = json_decode((string)($v['images'] ?? ''), true);
            $firstImg = (!empty($images) && is_array($images)) ? (string)($images[0]['url'] ?? '') : '';

            $impact = (float)$v['price'] - $defaultPrice;

            $xw->startElement('PRODUCT');

            $xw->writeElement('PRODUCT_ID',            (string)($v['ps_id_product'] ?? ''));
            $xw->writeElement('PRODUCT_REFERENCE',     (string)$rep['manufacturer_id']);
            $xw->writeElement('COMBINATION_REFERENCE', (string)$v['manufacturer_id']);
            $xw->writeElement('ATTRIBUTE_NAMES',       'Size');
            $xw->writeElement('ATTRIBUTE_VALUES',      (string)$v['size']);
            $xw->writeElement('SUPPLIER_REFERENCE',    (string)$v['manufacturer_id']);
            $xw->writeElement('SUPPLIER_PRICE',        number_format((float)$v['base_price'], 6, '.', ''));

            $xw->startElement('IMAGES');
            $xw->writeCdata($firstImg);
            $xw->endElement();

            $xw->writeElement('PRICE_TAX_EXCLUDED', (string)$v['price']);
            $xw->writeElement('PRICE_TAX_INCLUDED', (string)$v['price']);
            $xw->writeElement('IMPACT_ON_PRICE',    number_format($impact, 6, '.', ''));
            $xw->writeElement('QUANTITY',           (string)$v['avail_quantity']);
            $xw->writeElement('MINIMAL_QUANTITY',   '1');
            $xw->writeElement('DEFAULT',            $i === 0 ? '1' : '0');

            $xw->endElement(); // PRODUCT
        }
    }
}
