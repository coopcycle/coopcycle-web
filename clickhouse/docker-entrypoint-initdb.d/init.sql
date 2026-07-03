-- S3Queue
CREATE OR REPLACE TABLE ordersQueue (
    `restaurant` String,
    `order_code` String,
    `completed_at` Nullable(DateTime64(6)),
    `courier` Nullable(String),
    `fillfillment` String,
    `payment_method` String,
    `delivery_fee` Int32,
    `tip` Int32,
    `promotions` Int32,
    `total_products_excl_vat` Int32,
    `total_products_incl_vat` Int32,
    `total_incl_tax` Int32,
    `stripe_fee` Int32,
    `platform_fee` Int32,
    `refunds` Int32,
    `net_revenue` Int32,
    `billing_method` Enum('unit' = 1, 'percentage' = 2),
    `applied_billing` Enum('LASTMILE' = 1, 'FOODTECH' = 2),
) ENGINE = S3Queue('http://minio:9000/exports/orders/instance=*/year=*/month=*/*.parquet')
SETTINGS mode = 'ordered';

-- MergeTree
CREATE OR REPLACE TABLE orders (
    `restaurant` String,
    `order_code` String,
    `completed_at` Nullable(DateTime64(6)),
    `courier` Nullable(String),
    `fillfillment` String,
    `payment_method` String,
    `delivery_fee` Int32,
    `tip` Int32,
    `promotions` Int32,
    `total_products_excl_vat` Int32,
    `total_products_incl_vat` Int32,
    `total_incl_tax` Int32,
    `stripe_fee` Int32,
    `platform_fee` Int32,
    `refunds` Int32,
    `net_revenue` Int32,
    `billing_method` Enum('unit' = 1, 'percentage' = 2),
    `applied_billing` Enum('LASTMILE' = 1, 'FOODTECH' = 2),
    `instance` String,
    `month` String,
    `year` String
) ENGINE = MergeTree
PRIMARY KEY order_code;

-- MaterializedView
DROP VIEW IF EXISTS ordersConsumer;
CREATE MATERIALIZED VIEW ordersConsumer TO orders AS
SELECT *
        , extract(_path, 'instance=(\w+)\/') as instance
    , extract(_path, 'year=(\w+)\/') as year
    , extract(_path, 'month=(\w+)\/') as month
FROM ordersQueue;


-- S3Queue
CREATE OR REPLACE TABLE tasksQueue (
    id Int32,
    order_id Nullable(Int32),
    order_code Nullable(String),
    order_total Nullable(Int32),
    order_revenue Nullable(Int32),
    type Enum('PICKUP' = 0, 'DROPOFF' = 1),
    address Tuple(
        contact Nullable(String),
        name Nullable(String),
        street String,
        description Nullable(String),
        lat Float32,
        lng Float32
    ),
    after DateTime64(6),
    before DateTime64(6),
    status Enum('TODO' = 1, 'DOING' = 2, 'FAILED' = 3, 'DONE' = 4, 'CANCELLED' = 5),
    finished Nullable(DateTime64(6)),
    courier Nullable(String),
    organization Nullable(String)
) ENGINE = S3Queue('http://minio:9000/exports/tasks/instance=*/year=*/month=*/*.parquet')
SETTINGS mode = 'ordered';

-- MergeTree
CREATE OR REPLACE TABLE tasks (
    id Int32,
    order_id Nullable(Int32),
    order_code Nullable(String),
    order_total Nullable(Int32),
    order_revenue Nullable(Int32),
    type Enum('PICKUP' = 0, 'DROPOFF' = 1),
    address Tuple(
        contact Nullable(String),
        name Nullable(String),
        street String,
        description Nullable(String),
        lat Float32,
        lng Float32
    ),
    after DateTime64(6),
    before DateTime64(6),
    status Enum('TODO' = 1, 'DOING' = 2, 'FAILED' = 3, 'DONE' = 4, 'CANCELLED' = 5),
    finished Nullable(DateTime64(6)),
    courier Nullable(String),
    organization Nullable(String),
    `instance` String,
    `month` String,
    `year` String
) ENGINE = MergeTree
PRIMARY KEY id;

-- MaterializedView
DROP VIEW IF EXISTS tasksConsumer;
CREATE MATERIALIZED VIEW tasksConsumer TO tasks AS
SELECT *
        ,   extract(_path, 'instance=(\w+)\/') as instance
    , extract(_path, 'year=(\w+)\/') as year
    , extract(_path, 'month=(\w+)\/') as month
FROM tasksQueue;
