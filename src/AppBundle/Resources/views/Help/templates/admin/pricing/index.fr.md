Tarification
------------

Vous pouvez créer de nouvelles tarifications depuis le tableau de bord d'administration. Par la suite lorsque vous créerez un `Magasin` vous pourrez l'associer à une tarification. Celle-ci sera utilisée pour calculer le prix des courses pour ce magasin.

Chaque `tarification` est composée de plusieurs `règles`. Chaque `règle` est constituée de critères et d'un prix fixe. Lors du calcul du prix de la course les règles sont parcourues dans l'ordre, et la première qui correspond aux règles est appliquée.

Critères disponibles :
 - Poids (en grammes) : poids du colis à livrer
 - Distance (en mètres) : la longueur de la livraison
 - Type de véhicule : type de vélo, peut-être un `vélo` ou un `vélo cargo`
 - Zone : l'adresse de livraison est dans la zone
