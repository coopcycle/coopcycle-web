<?php

namespace AppBundle\Faker\it;

use AppBundle\Faker\RestaurantProvider as BaseRestaurantProvider;

class RestaurantProvider extends BaseRestaurantProvider
{
    /* Negozi */

    protected static $storeAdjectives = array(
        'express',
        'prompto',
        'home',
        'veloce',
    );

    protected static $storeActivities = array(
        'cucito',
        'lavaggio a secco',
        'fiori',
        'pacchi',
        'frutta e verdura',
        'fotografia',
        'occhiali'
    );

    /* Ristoranti */

    protected static $restaurantPrefixes = array(
        'la pentola', 'il piatto', 'il tavolo', 'la sedia', 'la cassa', 'il cucchiaio',
        'la melanzana', 'la mandorla', 'la patata',
        'la palla', 'la scatola', 'l’incudine',
        'l’île', 'l’auberge', 'la cabane',
    );

    protected static $restaurantAdjectives = array(
        'rosso', 'nero',
        'pois'
    );

    protected static $restaurantSuffixes = array(
        'che balla', 'che beve', 'che fuma', 'che ride', 'che canta',
        'avido', 'felice', 'intelligente', 'ribelle', 'sognatore', 'divertente',
        'meridionale', 'bretone', 'spagnolo', 'zingaro', 'orientale',
    );

    /* Antipasti */

    protected static $appetizerMainIngredients = array(
        'pomodori freschi', 'verdure crude', 'sottaceti', 'prosciutto crudo'
    );

    protected static $appetizerAdditionalIngredients = array(
        'in olio d’oliva', 'in insalata', 'al naturale'
    );

    /* Piatti */

    protected static $dishMainIngredients = array(
        'pollo', 'lumache', 'rane', 'melanzane', 'pomodori',
        'zucchine', 'patate', 'manzo', 'coniglio',
    );

    protected static $dishAdjectives = array(
        'arrosto', 'semi-cotto', 'in padella', 'ripieno', 'ecc.'
    );

    protected static $dishAdditionalIngredients = array(
        'con erbe', 'in salsa', 'con panna', 'con sale grosso', 'con vino rosso',
        'con mele', 'con carote', 'con cipolle', 'con burro', 'con peperoni',
    );

    /* Accompagnamenti / bevande */

    protected static $accompaniments = array(
        'patatine', 'insalata', 'purè di patate',
        'bulgur', 'carote grattugiate', 'piselli', 'verdure grigliate',
        'alghe marinate', 'germogli di bambù', 'insalata di cavoli',
    );

    protected static $drinks = array(
        'acqua', 'acqua frizzante',
        'succo di carota', 'ginseng', 'tè verde',
        'soda', 'limonata',
        'birra', 'vino',
    );

    /* Dessert */

    protected static $dessertMainIngredients = array(
        'crostata', 'torta', 'clafoutis',
        'moelleux', 'charlotte', 'crêpe', 'flan',
    );

    protected static $dessertAdditionalIngredients = array(
        'mela', 'cioccolato', 'fico', 'gocce di cioccolato',
        'uvetta', 'frutta candita', 'rabarbaro', 'zucchero a velo',
        'con caffè'
    );
}
