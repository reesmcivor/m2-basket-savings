<?php

namespace ReesMcIvor\BasketSavings\Block;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Cart;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\Currency;
use Magento\Catalog\Api\ProductRepositoryInterface;

class Savings extends Template
{
    protected $cart;
    protected $storeManager;
    protected $currency;
    protected $productRepository;

    public function __construct(
        Template\Context $context,
        Cart $cart,
        StoreManagerInterface $storeManager,
        Currency $currency,
        ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->cart = $cart;
        $this->storeManager = $storeManager;
        $this->currency = $currency;
        $this->productRepository = $productRepository;
    }

    public function getCartItems()
    {
        return $this->cart->getQuote()->getAllItems();
    }

    public function calculateSavings($item)
    {
        $originalPrice = $item->getProduct()->getPrice();
        $salePrice = $item->getPriceInclTax();
        if($salePrice > 0 && $salePrice < $originalPrice) {
            return $originalPrice - $salePrice;
        }
        return 0;
    }

    public function calculateTotalSavings()
    {
        try {
            $totalSavings = 0;
            $totalOriginalPrice = 0;

            $items = $this->getCartItems();
            foreach ($items as $item)
            {
                $itemQtys = [];
                switch ($item->getProduct()->getTypeId())
                {
                    case "simple":
                        $qty = $item->getParentItem() ? $item->getParentItem()->getQty() : $item->getQty();
                        $totalSavings += $this->calculateSavings($item) * $qty;
                        $totalOriginalPrice += $item->getProduct()->getPrice() * $qty;
                    break;
                }
            }
            $quote = $this->cart->getQuote();
            $discountAmount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
            $totalSavings += $discountAmount;

            return [
                'total_original_price' => $totalOriginalPrice,
                'total_savings' => $totalSavings,
            ];

        } catch (\Exception $e) {
            // silence is golden
        }
    }

    public function getCurrencySymbol()
    {
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
        return $this->currency->load($currencyCode)->getCurrencySymbol();
    }
}
