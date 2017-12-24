Pricing
-------

You can define rules for deliveries pricing:
each rule defines an « expression » and an associated price.

The rules are traversed in order, and the first one whose expression is evaluated to true is applied.

Available variables: <code>distance</code>, <code>weight</code>

<strong>Examples:</strong>

<code>distance in 0..3000</code> : the distance is between 0 and 3000 meters

<code>weight > 1000</code> : the weight is greater than 1000 grams

<a target="_blank" href="http://symfony.com/doc/3.4/components/expression_language/syntax.html">Expression syntax</a>
