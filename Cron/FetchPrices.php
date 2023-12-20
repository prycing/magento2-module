<?php

namespace Qualide\Prycing\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Qualide\Prycing\Model\Config;

class FetchPrices
{
    private Config $config;
    private ProductCollectionFactory $productCollectionFactory;
    private ResourceConnection $resourceConnection;
    private AdapterInterface $connection;
    private array $productAttributeCache = [];

    public function __construct(
        Config $config,
        ProductCollectionFactory $productCollectionFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->resourceConnection = $resourceConnection;
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
            $products[$sku] = [
                'sku' => (string)$productData->ean,
                'price' => (float)$productData->price,
                'special_price' => (float)$productData->special_price,
                'special_price_from' => (string)$productData->special_price_from,
                'special_price_to' => (string)$productData->special_price_to
            ];
        }

        $skus = array_column($products, 'sku');
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToFilter('sku', ['in' => $skus]);
        foreach ($productCollection as $product) {
            $products[$product->getSku()]['entity_id'] = $product->getId();
        }

        try {
            foreach ($products as $product) {
                if (!isset($product['entity_id'])) {
                    continue;
                }

                // Perform mass update of prices and special prices directly in the database
                $this->updateProductPriceBySku($product['entity_id'], $product['price']);
                $this->updateSpecialPriceBySku(
                    $product['entity_id'],
                    $product['special_price'],
                    $product['special_price_from'],
                    $product['special_price_to']
                );
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
     */
    private function updateProductPriceBySku(string $entityId, float $price): void
    {
        $this->updateProductAttribute($entityId, 'price', 'catalog_product_entity_decimal', $price);
    }

    /**
     * Update special price by SKU
     *
     * @param string $entityId
     * @param float|null $specialPrice
     * @param string|null $specialPriceFrom
     * @param string|null $specialPriceTo
     */
    private function updateSpecialPriceBySku(
        string $entityId,
        ?float $specialPrice,
        ?string $specialPriceFrom,
        ?string $specialPriceTo
    ): void {
        $decimalTable = "catalog_product_entity_decimal";
        $datetimeTable = "catalog_product_entity_datetime";
        if ($specialPrice) {
            $this->updateProductAttribute($entityId, 'special_price', $decimalTable, $specialPrice);
            $this->updateProductAttribute($entityId, 'special_from_date', $datetimeTable, $specialPriceFrom ?: null);
            $this->updateProductAttribute($entityId, 'special_to_date', $datetimeTable, $specialPriceTo ?: null);
        } else {
            // If special price is not set in XML, set it to null in the database
            $this->updateProductAttribute($entityId, 'special_price', $decimalTable, null);
            $this->updateProductAttribute($entityId, 'special_from_date', $datetimeTable, null);
            $this->updateProductAttribute($entityId, 'special_to_date', $datetimeTable, null);
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
     * @return void
     */
    public function updateProductAttribute(string $entityId, string $attribute, string $table, mixed $value): void
    {
        $tableName = $this->resourceConnection->getTableName($table);
        $attributeId = $this->getAttributeId($attribute);

        if ($value == null) {
            $this->connection->delete($tableName, ['attribute_id = ?' => $attributeId, 'entity_id = ?' => $entityId]);
            return;
        }

        $this->connection->insertOnDuplicate(
            $tableName,
            ['value' => $value, 'attribute_id' => $attributeId, 'store_id' => 0, 'entity_id' => $entityId], ['value']
        );
    }
}
