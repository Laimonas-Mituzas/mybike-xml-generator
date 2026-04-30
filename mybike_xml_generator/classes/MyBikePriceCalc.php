<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyBikePriceCalc
{
    private $priceKey;
    private $coefficient;
    private $withVat;
    private $taxRate;

    /**
     * @param string $priceKey      'price' or 'base_price'
     * @param float  $coefficient   multiplier (e.g. 1.25)
     * @param bool   $withVat       true if API price already includes VAT
     * @param float  $taxRate       VAT rate in percent (e.g. 21.0)
     */
    public function __construct($priceKey, $coefficient, $withVat, $taxRate)
    {
        $this->priceKey    = $priceKey;
        $this->coefficient = (float)$coefficient;
        $this->withVat     = (bool)$withVat;
        $this->taxRate     = (float)$taxRate;
    }

    /**
     * Returns PS price (excl. VAT) for a staging row.
     */
    public function calc(array $row)
    {
        $raw = (float)($row[$this->priceKey] ?? 0) * $this->coefficient;
        if ($this->withVat && $this->taxRate > 0) {
            return $raw / (1 + $this->taxRate / 100);
        }
        return $raw;
    }

    /**
     * Returns wholesale_price (the other price field, no coefficient).
     */
    public function wholesale(array $row)
    {
        $otherKey = ($this->priceKey === 'price') ? 'base_price' : 'price';
        return (float)($row[$otherKey] ?? 0);
    }

    public static function fromConfig()
    {
        $priceKey    = Configuration::get('MYBIKE_PRICE_KEY') ?: 'price';
        $coefficient = (float)(Configuration::get('MYBIKE_PRICE_COEFFICIENT') ?: 1.0);
        $withVat     = (bool)(int)Configuration::get('MYBIKE_PRICE_WITH_VAT');
        $taxRulesId  = (int)Configuration::get('MYBIKE_IMPORT_TAX_RULES_ID');

        $taxRate = 0.0;
        if ($withVat && $taxRulesId > 0) {
            $taxRate = self::getTaxRate($taxRulesId);
        }

        return new self($priceKey, $coefficient, $withVat, $taxRate);
    }

    private static function getTaxRate($taxRulesId)
    {
        $row = Db::getInstance()->getRow(
            'SELECT `rate` FROM `' . _DB_PREFIX_ . 'tax_rule` tr
             JOIN `' . _DB_PREFIX_ . 'tax` t ON t.`id_tax` = tr.`id_tax`
             WHERE tr.`id_tax_rules_group` = ' . (int)$taxRulesId . '
             LIMIT 1'
        );
        return $row ? (float)$row['rate'] : 0.0;
    }
}
