<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeStockXmlBuilder
{
    public function build(array $products, $outputPath)
    {
        $tmpPath = $outputPath . '.tmp';

        $writer = new XMLWriter();
        $writer->openUri($tmpPath);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('products');
        $writer->writeAttribute('generated', date('Y-m-d\TH:i:s'));
        $writer->writeAttribute('total', count($products));

        foreach ($products as $p) {
            $avail = $p['availability'] ?? [];

            $writer->startElement('product');
            $writer->writeElement('id',                 (string)($p['id'] ?? ''));
            $writer->writeElement('standard_item_id',   (string)($p['standard_item_id'] ?? ''));
            $writer->writeElement('manufacturer_id',    (string)($p['manufacturer_id'] ?? ''));
            $writer->writeElement('price',              (string)($p['price'] ?? ''));
            $writer->writeElement('base_price',         (string)($p['base_price'] ?? ''));
            $writer->startElement('availability');
            $writer->writeElement('status',            (string)($avail['status'] ?? ''));
            $writer->writeElement('quantity',          (string)($avail['quantity'] ?? 0));
            $writer->writeElement('availability_date', (string)($avail['availability_date'] ?? ''));
            $writer->endElement();
            $writer->endElement(); // product
        }

        $writer->endElement(); // products
        $writer->endDocument();
        $writer->flush();
        unset($writer);

        rename($tmpPath, $outputPath);
    }
}
