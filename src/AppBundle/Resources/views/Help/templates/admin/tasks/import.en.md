Import tasks
------------

To avoid creating lots of tasks manually, it is possible to import them via a CSV file.

On the dashboard, click on « Import » : a window opens to upload a file.

#### File format

You need to specify at least an address for each tasks, other columns are optional.

Columns `before` and `after` to specify the time window accept a wide variety of formats.

You can download an [example file](/help/tasks_import.example.fr.csv).

---


| `type`    | `address.name`      | `address`                             | `after`            | `before`           | `comments`           | `tags`      |
| --------- | ------------------- | ------------------------------------- | ------------------ | ------------------ | -------------------- | ----------- |
| `pickup`  | `Mairie du 2e`      | `1, rue de Rivoli Paris`              | `15/02/2018 12:00` | `15/02/2018 14:00` | `Call customer`      |             |
| `dropoff` | `Bijouterie`        | `54, rue de la Paix Paris`            | `2018-02-15 09:00` | `2018-02-15 10:00` |                      | `important` |
| `dropoff` | `Magasin de fleurs` | `23, rue du Faubourg du Temple Paris` | `09:00`            | `12:00`            |                      |             |
