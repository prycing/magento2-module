<?php

declare(strict_types=1);

namespace Prycing\Prycing\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributes implements OptionSourceInterface
{
    private CollectionFactory $attributeCollectionFactory;

    public function __construct(
        CollectionFactory $attributeCollectionFactory
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * Get all product attributes as options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [
            ['value' => 'sku', 'label' => 'SKU (Default)']
        ];

        $attributeCollection = $this->attributeCollectionFactory->create();
        $attributeCollection->addFieldToFilter('is_visible', 1)
            ->addFieldToFilter('is_global', 1)
            ->addFieldToFilter('frontend_input', ['in' => ['text', 'varchar']]);

        foreach ($attributeCollection as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')'
            ];
        }

        return $options;
    }
} 