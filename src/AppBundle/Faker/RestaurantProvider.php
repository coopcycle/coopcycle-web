<?php

namespace AppBundle\Faker;

use Faker\Provider\Base as BaseProvider;

class RestaurantProvider extends BaseProvider
{
    /* Formats */

    protected static $storeFormat = '{{storeActivity}} {{storeAdjective}}';

    protected static $restaurantFormats = array(
        '{{restaurantPrefix}} {{restaurantAdjective}} {{restaurantSuffix}}',
        '{{restaurantPrefix}} {{restaurantSuffix}}',
    );

    protected static $appetizerFormats = array(
        '{{appetizerMainIngredient}} {{appetizerAdditionalIngredient}}',
    );

    protected static $dishFormats = array(
        '{{dishMainIngredient}} {{dishAdjective}} {{dishAdditionalIngredient}}',
        '{{dishMainIngredient}} {{dishAdditionalIngredient}}',
    );

    protected static $dessertFormats = array(
        '{{dessertMainIngredient}} {{dessertAdditionalIngredient}}',
    );

    /* Stores */

    protected static $storeAdjectives = array(
        'express',
        'prompto',
        'à domicile',
        'rapide',
    );

    protected static $storeActivities = array(
        'couture',
        'pressing',
        'fleurs',
        'colis',
        'fruits & légumes',
        'photos',
        'lunettes'
    );

    public function storeAdjective()
    {
        return static::randomElement(static::$storeAdjectives);
    }

    public function storeActivity()
    {
        return static::randomElement(static::$storeActivities);
    }

    /* Restaurants */

    protected static $restaurantPrefixes = array(
        'la casserole', 'l\'assiette', 'la table', 'la chaise', 'la cagette', 'la cuillère',
        'l\'aubergine', 'l\'amande', 'la patate',
        'la boule', 'la boîte', 'l\'enclume',
        'l\'île', 'l\'auberge', 'la cabane',
    );

    protected static $restaurantAdjectives = array(
        'rouge', 'noire',
        'à pois'
    );

    protected static $restaurantSuffixes = array(
        'qui danse', 'qui boit', 'qui fume', 'qui rit', 'qui chante',
        'gourmande', 'joyeuse', 'maline', 'rebelle', 'rêveuse', 'rigolote',
        'du sud', 'bretonne', 'espagnole', 'gitane', 'orientale',
    );

    public function restaurantPrefix()
    {
        return static::randomElement(static::$restaurantPrefixes);
    }

    public function restaurantAdjective()
    {
        return static::randomElement(static::$restaurantAdjectives);
    }

    public function restaurantSuffix()
    {
        return static::randomElement(static::$restaurantSuffixes);
    }

    /* Appetizers */

    protected static $appetizerMainIngredients = array(
        'tomates fraîches', 'crudités', 'pickles', 'jambon cru'
    );

    protected static $appetizerAdditionalIngredients = array(
        'à l\'huile d\'olive', 'en salade', 'au naturel'
    );

    public function appetizerMainIngredient()
    {
        return static::randomElement(static::$appetizerMainIngredients);
    }

    public function appetizerAdditionalIngredient()
    {
        return static::randomElement(static::$appetizerAdditionalIngredients);
    }

    /* Dishes */

    protected static $dishMainIngredients = array(
        'poulet', 'escargots', 'grenouilles', 'aubergines', 'tomates',
        'courgettes', 'pommes de terre', 'boeuf', 'lapin',
    );

    protected static $dishAdjectives = array(
        'rôti·e·s', 'mi-cuit·e·s', 'poêlé·e·s', 'farci·e·s'
    );

    protected static $dishAdditionalIngredients = array(
        'aux herbes', 'en sauce', 'à la crème', 'au gros sel', 'au vin rouge',
        'aux pommes', 'aux carottes', 'aux oignons', 'au beurre', 'aux poivrons'
    );

    public function dishMainIngredient()
    {
        return static::randomElement(static::$dishMainIngredients);
    }

    public function dishAdjective()
    {
        return static::randomElement(static::$dishAdjectives);
    }

    public function dishAdditionalIngredient()
    {
        return static::randomElement(static::$dishAdditionalIngredients);
    }

    /* Desserts */

    protected static $dessertMainIngredients = array(
        'tarte', 'gâteau', 'clafoutis', 'moelleux', 'charlotte', 'crêpe', 'flan'
    );

    protected static $dessertAdditionalIngredients = array(
        'aux pommes', 'au chocolat', 'aux figues', 'aux pépites de chocolat',
        'aux raisons secs', 'aux fruits confits', 'à la rhubarbe', 'au sucre glace',
        'au café'
    );

    public function dessertMainIngredient()
    {
        return static::randomElement(static::$dessertMainIngredients);
    }

    public function dessertAdditionalIngredient()
    {
        return static::randomElement(static::$dessertAdditionalIngredients);
    }

    /* Menu Item Modifiers */

    protected static $calculusMethods = array(
        'ADD_MENUITEM_PRICE',
        'ADD_MODIFIER_PRICE',
        'FREE'
    );

    /* API */

    public function storeName()
    {
        return ucfirst($this->generator->parse(static::$storeFormat));
    }

    public function restaurantName()
    {
        $format = static::randomElement(static::$restaurantFormats);

        return ucfirst($this->generator->parse($format));
    }

    public function appetizerName()
    {
        $format = static::randomElement(static::$appetizerFormats);

        return ucfirst($this->generator->parse($format));
    }

    public function dishName()
    {
        $format = static::randomElement(static::$dishFormats);

        return ucfirst($this->generator->parse($format));
    }

    public function dessertName()
    {
        $format = static::randomElement(static::$dessertFormats);

        return ucfirst($this->generator->parse($format));
    }

    public function calculusMethod() {
        return static::randomElement(static::$calculusMethods);
    }
}
