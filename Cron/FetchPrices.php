<?php

namespace Prycing\Prycing\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Prycing\Prycing\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;

class FetchPrices
{
    private Config $config;
    private ProductCollectionFactory $productCollectionFactory;
    private ResourceConnection $resourceConnection;
    private AdapterInterface $connection;
    private array $productAttributeCache = [];
    private StoreManagerInterface $storeManager;

    public function __construct(
        Config $config,
        ProductCollectionFactory $productCollectionFactory,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
    }

    public function execute(): int
    {
        // Check if module is enabled, otherwise do nothing
        if (!$this->config->isEnabled()) {
            return 0;
        }

        // If enabled fetch the feed (xml) and parse it
        $feedUrl = $this->config->getFeedUrl();

        try {
            // Get the feed from the URL
            $xml = simplexml_load_file($feedUrl);
        } catch (\Exception) {
            // TODO: Log error
            return 0;
        }

        $xmlProducts = $xml->product;

        // Start a transaction to ensure data consistency
        $this->connection = $this->resourceConnection->getConnection();
        $this->connection->beginTransaction();

        $products = [];
        foreach ($xmlProducts as $productData) {
            $sku = (string)$productData->ean;
            $storePrices = [];
            
            // Handle store-specific prices if they exist
            if (isset($productData->store_prices)) {
                foreach ($productData->store_prices->store as $storePrice) {
                    $storeId = (string)$storePrice->store_id;
                    $storePrices[$storeId] = [
                        'price' => (float)$storePrice->price,
                        'special_price' => (float)$storePrice->special_price,
                        'special_price_from' => (string)$storePrice->special_price_from,
                        'special_price_to' => (string)$storePrice->special_price_to
                    ];
                }
            }

            // Default prices (store_id = 0)
            $products[$sku] = [
                'sku' => $sku,
                'store_prices' => $storePrices,
                'default_price' => [
                    'price' => (float)$productData->price,
                    'special_price' => (float)$productData->special_price,
                    'special_price_from' => (string)$productData->special_price_from,
                    'special_price_to' => (string)$productData->special_price_to
                ]
            ];
        }

        $skus = array_column($products, 'sku');
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToFilter($this->config->getEanField(), ['in' => $skus]);
        foreach ($productCollection as $product) {
            $products[$product->getData($this->config->getEanField())]['entity_id'] = $product->getId();
        }

        try {
            foreach ($products as $product) {
                if (!isset($product['entity_id'])) {
                    continue;
                }

                $entityId = $product['entity_id'];

                // Update default prices (store_id = 0)
                $this->updateProductPriceBySku($entityId, $product['default_price']['price'], 0);
                $this->updateSpecialPriceBySku(
                    $entityId,
                    $product['default_price']['special_price'],
                    $product['default_price']['special_price_from'],
                    $product['default_price']['special_price_to'],
                    0
                );

                // Update store-specific prices
                foreach ($product['store_prices'] as $storeId => $storePrice) {
                    $this->updateProductPriceBySku($entityId, $storePrice['price'], $storeId);
                    $this->updateSpecialPriceBySku(
                        $entityId,
                        $storePrice['special_price'],
                        $storePrice['special_price_from'],
                        $storePrice['special_price_to'],
                        $storeId
                    );
                }
            }

            // Commit the transaction if everything went well
            $this->connection->commit();
        } catch (\Exception) {
            // Rollback the transaction in case of an error
            $this->connection->rollBack();
            return 0;
        }

        return 0;
    }

    /**
     * Update product price by SKU
     *
     * @param string $entityId
     * @param float $price
     * @param int $storeId
     */
    private function updateProductPriceBySku(string $entityId, float $price, int $storeId): void
    {
        $this->updateProductAttribute($entityId, 'price', 'catalog_product_entity_decimal', $price, $storeId);
    }

    /**
     * Update special price by SKU
     *
     * @param string $entityId
     * @param float|null $specialPrice
     * @param string|null $specialPriceFrom
     * @param string|null $specialPriceTo
     * @param int $storeId
     */
    private function updateSpecialPriceBySku(
        string $entityId,
        ?float $specialPrice,
        ?string $specialPriceFrom,
        ?string $specialPriceTo,
        int $storeId
    ): void {
        $decimalTable = "catalog_product_entity_decimal";
        $datetimeTable = "catalog_product_entity_datetime";
        
        if ($specialPrice) {
            $this->updateProductAttribute($entityId, 'special_price', $decimalTable, $specialPrice, $storeId);
            $this->updateProductAttribute($entityId, 'special_from_date', $datetimeTable, $specialPriceFrom ?: null, $storeId);
            $this->updateProductAttribute($entityId, 'special_to_date', $datetimeTable, $specialPriceTo ?: null, $storeId);
        } else {
            // If special price is not set in XML, set it to null in the database
            $this->updateProductAttribute($entityId, 'special_price', $decimalTable, null, $storeId);
            $this->updateProductAttribute($entityId, 'special_from_date', $datetimeTable, null, $storeId);
            $this->updateProductAttribute($entityId, 'special_to_date', $datetimeTable, null, $storeId);
        }
    }

    /**
     * Get the attribute ID by attribute code
     *
     * @param string $attributeCode
     * @return int|null
     */
    private function getAttributeId(string $attributeCode): ?int
    {
        if (isset($this->productAttributeCache[$attributeCode])) {
            return $this->productAttributeCache[$attributeCode];
        }

        $attributeId = $this->connection->fetchOne(
            $this->connection->select()
                ->from($this->resourceConnection->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $attributeCode)
                ->limit(1)
        );

        $this->productAttributeCache[$attributeCode] = $attributeId;

        return $attributeId ? (int)$attributeId : null;
    }

    /**
     * Update product attribute
     *
     * @param string $entityId
     * @param string $attribute
     * @param string $table
     * @param mixed $value
     * @param int $storeId
     * @return void
     */
    public function updateProductAttribute(string $entityId, string $attribute, string $table, mixed $value, int $storeId): void
    {
        $tableName = $this->resourceConnection->getTableName($table);
        $attributeId = $this->getAttributeId($attribute);

        if ($value === null) {
            $this->connection->delete(
                $tableName,
                [
                    'attribute_id = ?' => $attributeId,
                    'entity_id = ?' => $entityId,
                    'store_id = ?' => $storeId
                ]
            );
            return;
        }

        $this->connection->insertOnDuplicate(
            $tableName,
            [
                'value' => $value,
                'attribute_id' => $attributeId,
                'store_id' => $storeId,
                'entity_id' => $entityId
            ],
            ['value']
        );
    }
}
