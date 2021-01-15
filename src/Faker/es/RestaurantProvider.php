<?php

namespace AppBundle\Faker\es;

use AppBundle\Faker\RestaurantProvider as BaseRestaurantProvider;

class RestaurantProvider extends BaseRestaurantProvider
{
    /* Stores */

    protected static $storeAdjectives = array(
        'express',
        'prompto',
        'en casa',
        'rápido',
    );

    protected static $storeActivities = array(
        'costura',
        'tintorería',
        'flores',
        'frutas y verduras',
        'fotos',
        'gafas'
    );

    /* Restaurants */

    protected static $restaurantPrefixes = array(
        'la sartén', 'la mesa', 'la silla', 'la caja', 'la cuchara',
        'la berenjena', 'la almendra', 'la patata',
        'la pelota',
        'la isla', 'la cabaña',
    );

    protected static $restaurantAdjectives = array(
        'roja', 'negra',
    );

    protected static $restaurantSuffixes = array(
        'que baila', 'que bebe', 'que fuma',
        'cantante', 'codiciosa', 'alegre', 'inteligente', 'rebelde', 'soñadora', 'divertida',
        'gitana', 'oriental', 'del sur',
    );

    /* Appetizers */

    protected static $appetizerMainIngredients = array(
        'tomates frescos', 'verduras crudas', 'encurtidos', 'jamón curado'
    );

    protected static $appetizerAdditionalIngredients = array(
        'con aceite de oliva', 'en ensalada', 'al natural'
    );

    /* Dishes */

    protected static $dishMainIngredients = array(
        'pollo', 'caracoles', 'ranas', 'berenjenas', 'tomates',
        'calabacín', 'patatas', 'carne', 'conejo'
    );

    protected static $dishAdjectives = array(
        'asado', 'medio asado', 'frito', 'relleno',
    );

    protected static $dishAdditionalIngredients = array(
        'con ierba', 'con salsa', 'con crema', 'con sal gruesa', 'con vino tinto',
        'con manzana', 'con zanahoria', 'con cebolla', 'con mantequilla', 'con pimienta'
    );

    /* Accompaniments / drinks */

    protected static $accompaniments = array(
        'patatas fritas', 'ensalada', 'puré',
        'bulgur', 'zanahorias', 'guisantes', 'verduras a la parrilla',
        'algas marinas marinadas', 'brotes de bambú', 'ensalada de col',
    );

    protected static $drinks = array(
        'agua', 'agua con gas',
        'zumo de zanahoria', 'ginseng', 'té verde',
        'gaseosa', 'limonada',
        'cerveza', 'vino',
    );

    /* Desserts */

    protected static $dessertMainIngredients = array(
        'tarta', 'pastel', 'clafoutis', 'meloso', 'charlotte', 'crepe', 'flan',
    );

    protected static $dessertAdditionalIngredients = array(
        'con manzana', 'al chocolate', 'con higo', 'chispas de chocolate',
        'con fruta confitada', 'con ruibarbo', 'con azúcar glasé',
        'con café',
    );
}
