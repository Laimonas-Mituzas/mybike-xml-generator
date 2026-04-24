<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeStockSync
{
    private $api;
    private $logger;
    private $stockBuilder;

    public function __construct(MyBikeApiClient $api, MyBikeLogger $logger)
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->stockBuilder = new MyBikeStockXmlBuilder();
    }

    public function run()
    {
        $start = microtime(true);
        $this->logger->info('Stock sync started');

        $products = $this->fetchAllProducts();
        $this->logger->info('Fetched ' . count($products) . ' products from API');

        $this->stockBuilder->build($products, MYBIKE_STOCK_XML);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info('Stock sync completed: ' . count($products) . ' products, ' . $duration . 's');

        return ['count' => count($products), 'duration' => $duration];
    }

    private function fetchAllProducts()
    {
        $products = [];
        $page = 1;

        do {
            $response = $this->api->getProducts($page);
            $products = array_merge($products, $response['data']);
            $meta = $response['meta'];
            $page++;
        } while ($page <= $meta['pages']);

        return $products;
    }
}
