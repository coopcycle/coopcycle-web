Tarification
------------

Lorsque vous créez un `Magasin` vous pouvez l'associer à une `Tarification`. Celle-ci sera utilisée pour calculer le prix des courses pour ce magasin.

#### Créer une tarification (administrateur seulement)

Vous pouvez créer de nouvelles tarifications depuis le tableau de bord d'administration.

Chaque `Tarification` est composée de plusieurs `Règles`. Chaque `Règle` est constituée de critères et d'un prix fixe. Lors du calcul du prix de la course les règles sont parcourues dans l'ordre, et la première qui correspond aux règles est appliquée.

Critères disponibles :

 * Poids (en grammes) : poids du colis à livrer
 * Distance (en mètres) : la longueur de la livraison
 * Type de véhicule : type de vélo, peut-être un `vélo` ou un `vélo cargo`
 * Zone : l'adresse de livraison est dans la zone

#### Assigner une tarification à un magasin (administrateur seulement)

Chaque `Magasin` a une `Tarification`, pour l'assigner :

 * lorsque celui-ci est créé via le bouton `Ajouter un magasin` de l'onglet `Magasins` du panel d'administration
 * plus tard via le bouton `Gérer` à droite de chaque magasin dans l'onglet `Magasins` du panel d'administration
 