<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeFullSync
{
    private $api;
    private $logger;
    private $fullBuilder;
    private $stockBuilder;
    private $onlyInStock;
    private $enabledIds;

    public function __construct(MyBikeApiClient $api, MyBikeLogger $logger, array $config = [])
    {
        $this->api          = $api;
        $this->logger       = $logger;
        $this->fullBuilder  = new MyBikeFullXmlBuilder();
        $this->stockBuilder = new MyBikeStockXmlBuilder();
        $this->onlyInStock  = !empty($config['only_in_stock']);
        $this->enabledIds   = isset($config['enabled_ids']) ? $config['enabled_ids'] : [];
    }

    public function run()
    {
        $start = microtime(true);
        $this->logger->info('Full sync started');

        $existing = $this->loadExisting();
        $this->logger->info('Loaded ' . count($existing) . ' existing products from XML');

        $apiProducts = $this->fetchAllProducts();
        $this->logger->info('Fetched ' . count($apiProducts) . ' products from API');

        $enriched = $this->enrichProducts($apiProducts, $existing);

        $this->fullBuilder->build($enriched, MYBIKE_FULL_XML);
        $this->stockBuilder->build($enriched, MYBIKE_STOCK_XML);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info('Full sync completed: ' . count($enriched) . ' products, ' . $duration . 's');

        return ['count' => count($enriched), 'duration' => $duration];
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
            $this->logger->info('Fetched page ' . $page . '/' . $meta['pages'] . ' (' . count($products) . ' products so far)');
            $page++;
        } while ($page <= $meta['pages']);

        return $products;
    }

    private function loadExisting()
    {
        if (!file_exists(MYBIKE_FULL_XML)) {
            return [];
        }

        $existing = [];

        try {
            $xml = simplexml_load_file(MYBIKE_FULL_XML);
            foreach ($xml->product as $p) {
                $images = [];
                foreach ($p->images->image as $img) {
                    $images[] = [
                        'url'      => (string)$img['url'],
                        'is_local' => (string)$img['is_local'] === '1',
                    ];
                }

                $existing[(int)$p->id] = [
                    'description'  => (string)$p->description,
                    'color'        => (string)$p->color,
                    'size'         => (string)$p->size,
                    'specs_raw'    => (string)$p->specs,
                    'featured'     => (string)$p->featured === '1',
                    'sub_category' => (string)$p->sub_category,
                    'images'       => $images,
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to load existing XML: ' . $e->getMessage());
        }

        return $existing;
    }

    private function enrichProducts(array $products, array $existing)
    {
        $enriched = [];
        $detailsFetched = 0;

        foreach ($products as $p) {
            $id = (int)$p['id'];

            if (!isset($existing[$id])) {
                try {
                    $detail = $this->api->getProduct($id);
                    $d = $detail['data'];
                    $p['description']  = $d['description'] ?? '';
                    $p['color']        = $d['color'] ?? '';
                    $p['size']         = $d['size'] ?? '';
                    $p['specs']        = $d['specs'] ?? null;
                    $p['featured']     = $d['featured'] ?? false;
                    $p['sub_category'] = $d['sub_category'] ?? '';

                    $imgResponse = $this->api->getImages($id);
                    $p['images'] = $imgResponse['data'] ?? [];
                    $detailsFetched++;
                } catch (Exception $e) {
                    $this->logger->error('Details fetch failed for product ' . $id . ': ' . $e->getMessage());
                    $p = array_merge($p, $this->emptyDetails());
                }
            } else {
                $ex = $existing[$id];
                $p['description']  = $ex['description'];
                $p['color']        = $ex['color'];
                $p['size']         = $ex['size'];
                $p['specs']        = $ex['specs_raw'] ? json_decode($ex['specs_raw'], true) : null;
                $p['featured']     = $ex['featured'];
                $p['sub_category'] = $ex['sub_category'];
                $p['images']       = $ex['images'];
            }

            $enriched[] = $p;
        }

        $this->logger->info('Detail API calls made: ' . $detailsFetched . ' (new products only)');

        return $enriched;
    }

    private function emptyDetails()
    {
        return [
            'description'  => '',
            'color'        => '',
            'size'         => '',
            'specs'        => null,
            'featured'     => false,
            'sub_category' => '',
            'images'       => [],
        ];
    }
}
