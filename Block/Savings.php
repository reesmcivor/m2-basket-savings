<?php

namespace ReesMcIvor\BasketSavings\Block;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Cart;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\Currency;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Savings extends Template
{
    protected $cart;
    protected $storeManager;
    protected $currency;
    protected $productRepository;
    protected $scopeConfig;


    public function __construct(
        Template\Context $context,
        Cart $cart,
        StoreManagerInterface $storeManager,
        Currency $currency,
        ProductRepositoryInterface $productRepository,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->cart = $cart;
        $this->storeManager = $storeManager;
        $this->currency = $currency;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
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
            $savingsDebug = [];

            foreach ($items as $item)
            {
                $itemQtys = [];
                $itemToUse = $item->getParentItem() && $item->getProduct()->getTypeId() == "configurable" ? $item->getParentItem() : $item;
                $qty = $itemToUse->getQty();

                $savingsDebug[$item->getProduct()->getSku()] = [
                    'originalPrice' => $itemToUse->getProduct()->getPrice(),
                    'salePrice' => $itemToUse->getPriceInclTax(),
                    'qty' => $qty,
                    'savings' => $this->calculateSavings($itemToUse) * $qty,
                ];

                switch ($item->getProduct()->getTypeId())
                {
                    default:
                        $totalSavings += $this->calculateSavings($item) * $qty;
                        $totalOriginalPrice += $itemToUse->getProduct()->getPrice() * $qty;
                    break;
                }
            }
            $quote = $this->cart->getQuote();
            $discountAmount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
            $totalSavings += $discountAmount;

            return [
                'debug' => $savingsDebug,
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

    public function getShowDebug()
    {
        return $this->scopeConfig->getValue(
            'basket_savings/general/show_debug',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getShouldShowSavings() : bool
    {
        return $this->scopeConfig->getValue('basket_savings/general/show_savings', ScopeInterface::SCOPE_STORE) == 1;
    }

    public function getDebugIpAddresses() : array
    {
        return array_map('trim', explode(",", $this->scopeConfig->getValue(
            'basket_savings/general/debug_ip_addresses',
            ScopeInterface::SCOPE_STORE
        )));
    }

    public function getIfShouldShowDebug() : bool
    {
        return $this->getShowDebug() &&
            in_array($this->getRequest()->getClientIp(true), $this->getDebugIpAddresses());
    }
}
