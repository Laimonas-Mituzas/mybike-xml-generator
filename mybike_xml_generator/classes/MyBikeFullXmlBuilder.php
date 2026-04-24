<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeFullXmlBuilder
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
            $writer->writeElement('brand',              (string)($p['brand'] ?? ''));
            $writer->writeElement('model',              (string)($p['model'] ?? ''));
            $writer->writeElement('type',               (string)($p['type'] ?? ''));
            $writer->writeElement('provider',           (string)($p['provider'] ?? ''));
            $writer->writeElement('section',            (string)($p['section'] ?? ''));
            $writer->writeElement('category',           (string)($p['category'] ?? ''));
            $writer->writeElement('category_id',        (string)($p['category_id'] ?? ''));
            $writer->writeElement('sub_category',       (string)($p['sub_category'] ?? ''));
            $writer->writeElement('price',              (string)($p['price'] ?? ''));
            $writer->writeElement('base_price',         (string)($p['base_price'] ?? ''));
            $writer->writeElement('color',              (string)($p['color'] ?? ''));
            $writer->writeElement('size',               (string)($p['size'] ?? ''));
            $writer->writeElement('featured',           !empty($p['featured']) ? '1' : '0');

            $writer->startElement('description');
            $writer->writeCdata((string)($p['description'] ?? ''));
            $writer->endElement();

            $specs = $p['specs'] ?? null;
            $writer->startElement('specs');
            $writer->writeCdata($specs ? json_encode($specs, JSON_UNESCAPED_UNICODE) : '');
            $writer->endElement();

            $writer->startElement('availability');
            $writer->writeElement('status',            (string)($avail['status'] ?? ''));
            $writer->writeElement('quantity',          (string)($avail['quantity'] ?? 0));
            $writer->writeElement('availability_date', (string)($avail['availability_date'] ?? ''));
            $writer->endElement();

            $writer->startElement('images');
            foreach ($p['images'] ?? [] as $img) {
                $writer->startElement('image');
                $writer->writeAttribute('url',      (string)($img['url'] ?? ''));
                $writer->writeAttribute('is_local', !empty($img['is_local']) ? '1' : '0');
                $writer->endElement();
            }
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
