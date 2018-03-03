Importer des tâches
-------------------

Pour éviter de créer manuellement un grand nombre de tâches, il est possible de les importer en masse via un fichier CSV.

Sur le tableau de bord, cliquez sur le bouton « Importer » : une fenêtre s'ouvre pour permettre d'uploader un fichier.

#### Format de fichier

Vous devez au minimum spécifier une adresse pour chaque tâche, les autres colonnes sont optionnelles.

Les colonnes `before` et `after` pour spécifier le créneau horaire acceptent des valeurs sous différents formats.

Vous pouvez télécharger un [fichier d'exemple](/help/tasks_import.example.fr.csv).

---


| `type`    | `address.name`      | `address`                             | `after`            | `before`           | `comments`           | `tags`      |
| --------- | ------------------- | ------------------------------------- | ------------------ | ------------------ | -------------------- | ----------- |
| `pickup`  | `Mairie du 2e`      | `1, rue de Rivoli Paris`              | `15/02/2018 12:00` | `15/02/2018 14:00` | `Appeller le client` |             |
| `dropoff` | `Bijouterie`        | `54, rue de la Paix Paris`            | `2018-02-15 09:00` | `2018-02-15 10:00` |                      | `important` |
| `dropoff` | `Magasin de fleurs` | `23, rue du Faubourg du Temple Paris` | `09:00`            | `12:00`            |                      |             |
