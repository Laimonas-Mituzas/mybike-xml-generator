<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeStockSync
{
    private $api;
    private $logger;
    private $stockBuilder;
    private $onlyInStock;
    private $enabledIds;

    public function __construct(MyBikeApiClient $api, MyBikeLogger $logger, array $config = [])
    {
        $this->api          = $api;
        $this->logger       = $logger;
        $this->stockBuilder = new MyBikeStockXmlBuilder();
        $this->onlyInStock  = !empty($config['only_in_stock']);
        $this->enabledIds   = isset($config['enabled_ids']) ? $config['enabled_ids'] : [];
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
        $filters = [];
        if ($this->onlyInStock) {
            $filters['in_stock'] = 1;
        }

        $products = [];
        $page = 1;

        do {
            $response = $this->api->getProducts($page, $filters);
            $batch    = $response['data'];
            if (!empty($this->enabledIds)) {
                $batch = array_filter($batch, function ($p) {
                    return in_array((int)$p['category_id'], $this->enabledIds, true);
                });
            }
            $products = array_merge($products, array_values($batch));
            $meta = $response['meta'];
            $page++;
        } while ($page <= $meta['pages']);

        return $products;
    }
}
