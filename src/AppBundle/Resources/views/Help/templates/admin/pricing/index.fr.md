Tarification
------------

Chaque magasin a une tarification associée, qui permet de calculer le prix de la course. Vous pouvez définir des règles pour la tarification des livraisons. 

Chaque règle permet de définir une « expression » et un prix associé.

Les règles sont parcourues dans l'ordre, et la première dont l'expression est évaluée comme vraie est appliquée.

### Variables disponibles

`deliveryAddress` : L'adresse de livraison

`distance` : La distance entre le point de retrait et le point de dépôt

`weight` : Le poids du colis transporté en grammes

`vehicle` : Le type de véhicule (`bike` ou `cargo_bike`)

### Fonctions disponibles

`in_zone(address, zoneName)` : Vérifie que la variable `address` est dans la zone avec le nom <code>zoneName</code>

### Exemples

La distance est comprise entre 0 et 3000 mètres

<code>distance in 0..3000</code>

Le poids est supérieur à 1000 grammes

<code>weight > 1000</code>

La distance est comprise entre 0 et 3000 mètres et le poids est supérieur à 1000 grammes

<code>distance in 0..3000 and weight > 1000</code>

L'adresse de livraison est dans la zone « paris_est »

<code>in_zone(deliveryAddress, "paris_est")</code>

Le véhicule utilisé est un vélo cargo

<code>vehicle == "cargo_bike"</code>

<a target="_blank" href="http://symfony.com/doc/3.4/components/expression_language/syntax.html">Syntaxe des expressions</a>
