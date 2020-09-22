<?php

namespace AppBundle\Faker\fr;

use AppBundle\Faker\RestaurantProvider as BaseRestaurantProvider;

class RestaurantProvider extends BaseRestaurantProvider
{
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

    /* Appetizers */

    protected static $appetizerMainIngredients = array(
        'tomates fraîches', 'crudités', 'pickles', 'jambon cru'
    );

    protected static $appetizerAdditionalIngredients = array(
        'à l\'huile d\'olive', 'en salade', 'au naturel'
    );

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

    /* Accompaniments / drinks */

    protected static $accompaniments = array(
        'frites', 'salade', 'gratin dauphinois', 'purée',
        'boulgour', 'carottes râpées', 'petits pois', 'légumes grillés',
        'algues marinées', 'pousses de bambou', 'salade de chou'
    );

    protected static $drinks = array(
        'eau', 'eau pétillante',
        'jus de carotte', 'ginseng', 'thé vert',
        'soda', 'limonade',
        'bière', 'vin',
    );

    /* Desserts */

    protected static $dessertMainIngredients = array(
        'tarte', 'gâteau', 'clafoutis', 'moelleux', 'charlotte', 'crêpe', 'flan'
    );

    protected static $dessertAdditionalIngredients = array(
        'aux pommes', 'au chocolat', 'aux figues', 'aux pépites de chocolat',
        'aux raisons secs', 'aux fruits confits', 'à la rhubarbe', 'au sucre glace',
        'au café'
    );
}
