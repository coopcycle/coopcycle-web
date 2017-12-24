Tarification
------------

Vous pouvez définir des règles pour la tarification des livraisons :
chaque règle permet de définir une « expression » et un prix associé.

Les règles sont parcourues dans l'ordre, et la première dont l'expression est évaluée comme vraie est appliquée.

Variables disponibles : <code>distance</code>, <code>weight</code>

<strong>Exemples :</strong>

<code>distance in 0..3000</code> : la distance est comprise entre 0 et 3000 mètres

<code>weight > 1000</code> : le poids est supérieur à 1000 grammes

<a target="_blank" href="http://symfony.com/doc/3.4/components/expression_language/syntax.html">Syntaxe des expressions</a>
