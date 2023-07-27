<?php

namespace ReesMcIvor\BasketSavings\Block;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Cart;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\Currency;

class Savings extends Template
{
    protected $cart;
    protected $storeManager;
    protected $currency;

    public function __construct(
        Template\Context $context,
        Cart $cart,
        StoreManagerInterface $storeManager,
        Currency $currency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->cart = $cart;
        $this->storeManager = $storeManager;
        $this->currency = $currency;
    }

    public function getCartItems()
    {
        return $this->cart->getQuote()->getAllVisibleItems();
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
            foreach ($items as $item) {
                if ($item->getTypeId() != "bundle") {
                    $totalSavings += $this->calculateSavings($item);
                    $totalOriginalPrice += $item->getProduct()->getPrice() * $item->getQty();
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
            // silance
        }
    }

    public function getCurrencySymbol()
    {
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
        $currencySymbol = $this->currency->load($currencyCode)->getCurrencySymbol();

        return $currencySymbol;
    }
}
