<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikeApiSync
{
    private $api;
    private $logger;
    private $pdo;
    private $onlyInStock;
    private $enabledIds;
    private $table;

    public function __construct(MyBikeApiClient $api, MyBikeLogger $logger, PDO $pdo, array $config = [])
    {
        $this->api         = $api;
        $this->logger      = $logger;
        $this->pdo         = $pdo;
        $this->onlyInStock = !empty($config['only_in_stock']);
        $this->enabledIds  = $config['enabled_ids'] ?? [];
        $this->table       = '`' . _DB_PREFIX_ . 'mybike_product`';
    }

    /**
     * Full sync: fetches list + lazy detail for new IDs, UPSERTs all, marks deleted.
     */
    public function runFull(): array
    {
        $syncStart = date('Y-m-d H:i:s');
        $start     = microtime(true);
        $this->logger->info('API full sync started');

        $existingIds  = $this->loadExistingIds();
        $listProducts = $this->fetchAllProducts();
        $this->logger->info('Fetched ' . count($listProducts) . ' products from API');

        $detailsFetched = 0;

        foreach ($listProducts as $p) {
            $id = (int)$p['id'];

            if (!isset($existingIds[$id])) {
                try {
                    $detail     = $this->api->getProduct($id);
                    $d          = $detail['data'];
                    $imgResp    = $this->api->getImages($id);

                    $color = (string)($d['color'] ?? '');
                    $size  = $this->normalizeSize((string)($d['size'] ?? ''));

                    $p['description']           = (string)($d['description'] ?? '');
                    $p['color']                 = $color;
                    $p['size']                  = $size;
                    $p['color_comb_product_id'] = $color;
                    $p['size_comb_product_id']  = $size;
                    $p['specs']                 = isset($d['specs']) && $d['specs']
                                                    ? json_encode($d['specs'], JSON_UNESCAPED_UNICODE)
                                                    : null;
                    $p['featured']              = !empty($d['featured']) ? 1 : 0;
                    $p['sub_category']          = (string)($d['sub_category'] ?? '');
                    $p['images']                = json_encode($imgResp['data'] ?? [], JSON_UNESCAPED_UNICODE);

                    $this->upsertFull($p);
                    $detailsFetched++;
                } catch (Exception $e) {
                    $this->logger->error('Detail fetch failed for #' . $id . ': ' . $e->getMessage());
                }
            } else {
                $this->upsertLight($p);
            }
        }

        $this->markDeleted($syncStart);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info(
            'API full sync done: ' . count($listProducts) . ' products, '
            . $detailsFetched . ' detail calls, ' . $duration . 's'
        );

        return ['count' => count($listProducts), 'details_fetched' => $detailsFetched, 'duration' => $duration];
    }

    /**
     * Stock-only sync: updates price + availability fields, no detail fetch.
     */
    public function runStockOnly(): array
    {
        $syncStart = date('Y-m-d H:i:s');
        $start     = microtime(true);
        $this->logger->info('API stock sync started');

        $listProducts = $this->fetchAllProducts();
        $this->logger->info('Fetched ' . count($listProducts) . ' products from API');

        foreach ($listProducts as $p) {
            $this->upsertStock($p);
        }

        $this->markDeleted($syncStart);

        $duration = (int)round(microtime(true) - $start);
        $this->logger->info('API stock sync done: ' . count($listProducts) . ' products, ' . $duration . 's');

        return ['count' => count($listProducts), 'duration' => $duration];
    }

    private function fetchAllProducts(): array
    {
        $filters = [];
        if ($this->onlyInStock) {
            $filters['in_stock'] = 1;
        }

        $products = [];
        $page     = 1;

        do {
            $response = $this->api->getProducts($page, $filters);
            $batch    = $response['data'];

            if (!empty($this->enabledIds)) {
                $batch = array_values(array_filter($batch, function ($p) {
                    return in_array((int)$p['category_id'], $this->enabledIds, true);
                }));
            }

            $products = array_merge($products, $batch);
            $meta     = $response['meta'];
            $this->logger->info('Fetched page ' . $page . '/' . $meta['pages']);
            $page++;
        } while ($page <= $meta['pages']);

        return $products;
    }

    private function loadExistingIds(): array
    {
        $stmt = $this->pdo->query('SELECT `mybike_id` FROM ' . $this->table);
        $ids  = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[(int)$id] = true;
        }
        return $ids;
    }

    // Full UPSERT — all fields including detail (for new products after detail fetch)
    private function upsertFull(array $p): void
    {
        $avail     = $p['availability'] ?? [];
        $availDate = $this->parseDate((string)($avail['availability_date'] ?? ''));

        $sql = 'INSERT INTO ' . $this->table . '
            (`mybike_id`, `standard_item_id`, `manufacturer_id`, `brand`, `model`, `type`, `section`,
             `category`, `category_id`, `sub_category`, `price`, `base_price`, `color`, `size`,
             `color_comb_product_id`, `size_comb_product_id`, `featured`, `description`, `specs`, `images`,
             `avail_status`, `avail_quantity`, `avail_date`, `date_upd`)
            VALUES
            (:mybike_id, :standard_item_id, :manufacturer_id, :brand, :model, :type, :section,
             :category, :category_id, :sub_category, :price, :base_price, :color, :size,
             :color_comb_product_id, :size_comb_product_id, :featured, :description, :specs, :images,
             :avail_status, :avail_quantity, :avail_date, NOW())
            ON DUPLICATE KEY UPDATE
              `standard_item_id`      = VALUES(`standard_item_id`),
              `manufacturer_id`       = VALUES(`manufacturer_id`),
              `brand`                 = VALUES(`brand`),
              `model`                 = VALUES(`model`),
              `type`                  = VALUES(`type`),
              `section`               = VALUES(`section`),
              `category`              = VALUES(`category`),
              `category_id`           = VALUES(`category_id`),
              `sub_category`          = VALUES(`sub_category`),
              `price`                 = VALUES(`price`),
              `base_price`            = VALUES(`base_price`),
              `color`                 = VALUES(`color`),
              `size`                  = VALUES(`size`),
              `color_comb_product_id` = VALUES(`color_comb_product_id`),
              `size_comb_product_id`  = VALUES(`size_comb_product_id`),
              `featured`              = VALUES(`featured`),
              `description`           = VALUES(`description`),
              `specs`                 = VALUES(`specs`),
              `images`                = VALUES(`images`),
              `avail_status`          = VALUES(`avail_status`),
              `avail_quantity`        = VALUES(`avail_quantity`),
              `avail_date`            = VALUES(`avail_date`),
              `date_upd`              = NOW()';

        $this->pdo->prepare($sql)->execute([
            ':mybike_id'             => (int)$p['id'],
            ':standard_item_id'      => (string)($p['standard_item_id'] ?? ''),
            ':manufacturer_id'       => (string)($p['manufacturer_id'] ?? ''),
            ':brand'                 => (string)($p['brand'] ?? ''),
            ':model'                 => (string)($p['model'] ?? ''),
            ':type'                  => (string)($p['type'] ?? ''),
            ':section'               => (string)($p['section'] ?? ''),
            ':category'              => (string)($p['category'] ?? ''),
            ':category_id'           => (int)($p['category_id'] ?? 0),
            ':sub_category'          => (string)($p['sub_category'] ?? ''),
            ':price'                 => (float)($p['price'] ?? 0),
            ':base_price'            => (float)($p['base_price'] ?? 0),
            ':color'                 => (string)($p['color'] ?? ''),
            ':size'                  => (string)($p['size'] ?? ''),
            ':color_comb_product_id' => (string)($p['color_comb_product_id'] ?? ''),
            ':size_comb_product_id'  => (string)($p['size_comb_product_id'] ?? ''),
            ':featured'              => (int)($p['featured'] ?? 0),
            ':description'           => (string)($p['description'] ?? ''),
            ':specs'                 => $p['specs'] ?? null,
            ':images'                => $p['images'] ?? null,
            ':avail_status'          => (string)($avail['status'] ?? ''),
            ':avail_quantity'        => (int)($avail['quantity'] ?? 0),
            ':avail_date'            => $availDate,
        ]);
    }

    // Light UPSERT — list-level fields only; does NOT overwrite detail fields
    private function upsertLight(array $p): void
    {
        $avail     = $p['availability'] ?? [];
        $availDate = $this->parseDate((string)($avail['availability_date'] ?? ''));

        $sql = 'INSERT INTO ' . $this->table . '
            (`mybike_id`, `standard_item_id`, `manufacturer_id`, `brand`, `model`, `type`, `section`,
             `category`, `category_id`, `price`, `base_price`, `avail_status`, `avail_quantity`, `avail_date`, `date_upd`)
            VALUES
            (:mybike_id, :standard_item_id, :manufacturer_id, :brand, :model, :type, :section,
             :category, :category_id, :price, :base_price, :avail_status, :avail_quantity, :avail_date, NOW())
            ON DUPLICATE KEY UPDATE
              `standard_item_id` = VALUES(`standard_item_id`),
              `manufacturer_id`  = VALUES(`manufacturer_id`),
              `brand`            = VALUES(`brand`),
              `model`            = VALUES(`model`),
              `type`             = VALUES(`type`),
              `section`          = VALUES(`section`),
              `category`         = VALUES(`category`),
              `category_id`      = VALUES(`category_id`),
              `price`            = VALUES(`price`),
              `base_price`       = VALUES(`base_price`),
              `avail_status`     = VALUES(`avail_status`),
              `avail_quantity`   = VALUES(`avail_quantity`),
              `avail_date`       = VALUES(`avail_date`),
              `date_upd`         = NOW()';

        $this->pdo->prepare($sql)->execute([
            ':mybike_id'        => (int)$p['id'],
            ':standard_item_id' => (string)($p['standard_item_id'] ?? ''),
            ':manufacturer_id'  => (string)($p['manufacturer_id'] ?? ''),
            ':brand'            => (string)($p['brand'] ?? ''),
            ':model'            => (string)($p['model'] ?? ''),
            ':type'             => (string)($p['type'] ?? ''),
            ':section'          => (string)($p['section'] ?? ''),
            ':category'         => (string)($p['category'] ?? ''),
            ':category_id'      => (int)($p['category_id'] ?? 0),
            ':price'            => (float)($p['price'] ?? 0),
            ':base_price'       => (float)($p['base_price'] ?? 0),
            ':avail_status'     => (string)($avail['status'] ?? ''),
            ':avail_quantity'   => (int)($avail['quantity'] ?? 0),
            ':avail_date'       => $availDate,
        ]);
    }

    // Stock UPSERT — only updates stock fields on existing rows; inserts skeleton for new
    private function upsertStock(array $p): void
    {
        $avail     = $p['availability'] ?? [];
        $availDate = $this->parseDate((string)($avail['availability_date'] ?? ''));

        $sql = 'INSERT INTO ' . $this->table . '
            (`mybike_id`, `standard_item_id`, `manufacturer_id`, `brand`, `model`, `type`, `section`,
             `category`, `category_id`, `price`, `base_price`, `avail_status`, `avail_quantity`, `avail_date`, `date_upd`)
            VALUES
            (:mybike_id, :standard_item_id, :manufacturer_id, :brand, :model, :type, :section,
             :category, :category_id, :price, :base_price, :avail_status, :avail_quantity, :avail_date, NOW())
            ON DUPLICATE KEY UPDATE
              `price`          = VALUES(`price`),
              `base_price`     = VALUES(`base_price`),
              `avail_status`   = VALUES(`avail_status`),
              `avail_quantity` = VALUES(`avail_quantity`),
              `avail_date`     = VALUES(`avail_date`),
              `date_upd`       = NOW()';

        $this->pdo->prepare($sql)->execute([
            ':mybike_id'        => (int)$p['id'],
            ':standard_item_id' => (string)($p['standard_item_id'] ?? ''),
            ':manufacturer_id'  => (string)($p['manufacturer_id'] ?? ''),
            ':brand'            => (string)($p['brand'] ?? ''),
            ':model'            => (string)($p['model'] ?? ''),
            ':type'             => (string)($p['type'] ?? ''),
            ':section'          => (string)($p['section'] ?? ''),
            ':category'         => (string)($p['category'] ?? ''),
            ':category_id'      => (int)($p['category_id'] ?? 0),
            ':price'            => (float)($p['price'] ?? 0),
            ':base_price'       => (float)($p['base_price'] ?? 0),
            ':avail_status'     => (string)($avail['status'] ?? ''),
            ':avail_quantity'   => (int)($avail['quantity'] ?? 0),
            ':avail_date'       => $availDate,
        ]);
    }

    private function markDeleted(string $syncStart): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . $this->table . "
             SET `avail_status` = 'deleted', `date_upd` = NOW()
             WHERE `date_upd` < :sync_start AND `avail_status` != 'deleted'"
        );
        $stmt->execute([':sync_start' => $syncStart]);
        $deleted = $stmt->rowCount();
        if ($deleted > 0) {
            $this->logger->info('Marked ' . $deleted . ' products as deleted');
        }
    }

    private function normalizeSize(string $size): string
    {
        $upper = strtoupper(trim($size));
        if ($upper === 'UNIQUE' || $upper === 'U') {
            return '';
        }
        return $size;
    }

    private function parseDate(string $date): ?string
    {
        if ($date === '') {
            return null;
        }
        $ts = strtotime($date);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
