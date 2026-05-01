<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Generates products_stock.xml from ps_mybike_product staging table.
 * One <product> per row: id, price, availability, ps_id_product.
 */
class MyBikeStockDbXml
{
    private $outputFile;
    private $logger;

    public function __construct(string $outputFile, MyBikeLogger $logger)
    {
        $this->outputFile = $outputFile;
        $this->logger     = $logger;
    }

    public function build(): int
    {
        $start = microtime(true);
        $this->logger->info('Stock XML build started');

        $tmp = $this->outputFile . '.tmp';
        $xw  = new XMLWriter();
        $xw->openURI($tmp);
        $xw->startDocument('1.0', 'UTF-8');
        $xw->setIndent(false);
        $xw->startElement('products');
        $xw->writeAttribute('generated', date('c'));

        $total  = 0;
        $offset = 0;
        $limit  = 500;

        do {
            $rows = Db::getInstance()->executeS(
                "SELECT `mybike_id`, `standard_item_id`, `manufacturer_id`,
                        `price`, `base_price`, `avail_status`,
                        `avail_quantity`, `avail_date`, `ps_id_product`, `ps_id_product_attr`
                 FROM `" . _DB_PREFIX_ . "mybike_product`
                 WHERE `avail_status` != 'deleted'
                 ORDER BY `mybike_id`
                 LIMIT " . $limit . " OFFSET " . $offset
            );

            foreach ($rows as $row) {
                $xw->startElement('product');
                $xw->writeElement('id',               (string)$row['mybike_id']);
                $xw->writeElement('standard_item_id', (string)$row['standard_item_id']);
                $xw->writeElement('manufacturer_id',  (string)$row['manufacturer_id']);
                $xw->writeElement('price',            (string)$row['price']);
                $xw->writeElement('base_price',       (string)$row['base_price']);
                $xw->writeElement('availability_status', (string)$row['avail_status']);
                $xw->writeElement('availability_date',   (string)($row['avail_date'] ?? ''));
                $xw->writeElement('quantity',            (string)$row['avail_quantity']);
                $xw->writeElement('ps_id_product',      (string)($row['ps_id_product'] ?? ''));
                $xw->writeElement('ps_id_product_attr', (string)($row['ps_id_product_attr'] ?? ''));
                $xw->endElement(); // product
                $total++;
            }

            $offset += $limit;
        } while (count($rows) === $limit);

        $xw->endElement();
        $xw->flush();
        unset($xw);

        rename($tmp, $this->outputFile);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info('Stock XML done: ' . $total . ' products, ' . $duration . 's');

        return $total;
    }
}
