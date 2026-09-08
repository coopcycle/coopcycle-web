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

-- ---------------------------------------------------------------------------
-- v2: append-only exports
--
-- The v1 pipeline above has two structural faults, both fixed here:
--
--   1. mode = 'ordered' keeps a max-processed-path pointer and skips every file
--      sorting below it. Because the path starts with instance=, the ordering
--      is alphabetical by instance rather than chronological: once a file under
--      instance=ziclop (the last instance alphabetically) was processed, every
--      other instance's files were marked Processed with rows_processed = 0 and
--      never opened. DO NOT set mode = 'ordered' here.
--
--   2. S3Queue identifies files by path only, with no ETag or mtime. The v1
--      export rewrote a day's file when a task completed late, and those
--      rewrites were never re-read. v2 files are immutable: they are named
--      after the export run, never the day the rows belong to.
--
-- Re-reading a file is now harmless, which is what makes the queue safe to
-- reset and the whole bucket safe to replay: tasks_v2 is a ReplacingMergeTree
-- keyed on (instance, id) and versioned by exported_at, so the most recently
-- exported copy of a row wins whatever order the files arrive in.
--
-- There is deliberately no PARTITION BY: ReplacingMergeTree only deduplicates
-- within a partition, and a task can be rescheduled, which moves `after` and
-- would leave the old and new copies in different partitions.
-- ---------------------------------------------------------------------------

-- S3Queue
CREATE OR REPLACE TABLE tasksQueue_v2 (
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
    updated_at Nullable(DateTime64(6)),
    exported_at DateTime64(6)
) ENGINE = S3Queue('http://minio:9000/exports/v2/tasks/instance=*/exported=*/*.parquet')
SETTINGS
  mode = 'unordered',
  tracked_files_limit = 500000,
  enable_logging_to_queue_log = 1;

-- MergeTree
CREATE OR REPLACE TABLE tasks_v2 (
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
    `year` String,
    updated_at Nullable(DateTime64(6)),
    exported_at DateTime64(6)
) ENGINE = ReplacingMergeTree(exported_at)
ORDER BY (instance, id);

-- MaterializedView
-- instance now travels inside the file, so it is no longer parsed out of the
-- path: the old extract(_path, 'instance=(\w+)') silently truncated any
-- instance name containing a hyphen. month/year are kept for the Cube.js
-- dimensions of the same name, derived from the task's own dates.
DROP VIEW IF EXISTS tasksConsumer_v2;
CREATE MATERIALIZED VIEW tasksConsumer_v2 TO tasks_v2 AS
SELECT *
    , formatDateTime(after, '%Y') as year
    , formatDateTime(after, '%m') as month
FROM tasksQueue_v2;

-- S3Queue
CREATE OR REPLACE TABLE ordersQueue_v2 (
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
    updated_at Nullable(DateTime64(6)),
    exported_at DateTime64(6)
) ENGINE = S3Queue('http://minio:9000/exports/v2/orders/instance=*/exported=*/*.parquet')
SETTINGS
  mode = 'unordered',
  tracked_files_limit = 500000,
  enable_logging_to_queue_log = 1;

-- MergeTree
CREATE OR REPLACE TABLE orders_v2 (
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
    `year` String,
    updated_at Nullable(DateTime64(6)),
    exported_at DateTime64(6)
) ENGINE = ReplacingMergeTree(exported_at)
ORDER BY (instance, order_code);

-- MaterializedView
DROP VIEW IF EXISTS ordersConsumer_v2;
CREATE MATERIALIZED VIEW ordersConsumer_v2 TO orders_v2 AS
SELECT *
    , ifNull(formatDateTime(completed_at, '%Y'), '') as year
    , ifNull(formatDateTime(completed_at, '%m'), '') as month
FROM ordersQueue_v2;
