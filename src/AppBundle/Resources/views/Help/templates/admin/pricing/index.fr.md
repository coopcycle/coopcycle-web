Tarification
------------

Vous pouvez définir des règles pour la tarification des livraisons.

Chaque règle permet de définir une « expression » et un prix associé.

Les règles sont parcourues dans l'ordre, et la première dont l'expression est évaluée comme vraie est appliquée.

### Variables disponibles

<code>distance</code> : La distance entre le point de retrait et le point de dépôt

<code>weight</code> : Le poids du colis transporté

<code>deliveryAddress</code> : L'adresse de livraison

### Fonctions disponibles

<code>in_zone(address, zoneName)</code> : Vérifie que la variable <code>address</code> est dans la zone avec le nom <code>zoneName</code>

### Exemples

La distance est comprise entre 0 et 3000 mètres

<code>distance in 0..3000</code>

Le poids est supérieur à 1000 grammes

<code>weight > 1000</code>

La distance est comprise entre 0 et 3000 mètres et le poids est supérieur à 1000 grammes

<code>distance in 0..3000 and weight > 1000</code>

L'adresse de livraison est dans la zone « paris_est »

<code>in_zone(deliveryAddress, "paris_est")</code>

<a target="_blank" href="http://symfony.com/doc/3.4/components/expression_language/syntax.html">Syntaxe des expressions</a>
