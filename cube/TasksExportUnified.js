cube(`TasksExportUnified`, {
  sql: `
  SELECT
    DISTINCT ON (t.id) t.id,
    tci.position AS task_position,
    o.id AS order_id,
    o.number AS order_number,
    o.total AS order_total,
    o.state AS order_state,
    (SELECT SUM(platform_fee.amount) FROM ${PlatformFee.sql()} platform_fee WHERE platform_fee.order_id = o.id GROUP BY platform_fee.order_id) AS order_fee_total,
    (SELECT SUM(stripe_fee.amount) FROM ${StripeFee.sql()} stripe_fee WHERE stripe_fee.order_id = o.id GROUP BY stripe_fee.order_id) AS order_stripe_fee_total,
    t.type AS task_type,
    a.name AS address_name,
    a.street_address AS address_street_address,
    ST_AsText(a.geo) AS address_geo,
    a.description AS address_description,
    t.done_after AS after,
    t.done_before AS before,
    t.status AS status,
    t.comments AS comments,
    task_done.data AS task_done_data,
    task_failed.data AS task_failed_data,
    task_finished.created_at AS task_finished_at,
    u.username AS task_courier,
    (SELECT string_agg(TaskTagging.tag_name, ', ') FROM ${TaskTagging.sql()} TaskTagging WHERE TaskTagging.resource_id = t.id GROUP BY TaskTagging.resource_id) AS tags,
    a.contact_name AS address_contact_name,
    org.name AS task_organization_name
  FROM task t
  JOIN address a ON a.id = t.address_id
  LEFT JOIN task_collection_item tci ON tci.task_id = t.id
  LEFT JOIN task_list tl ON tl.id = tci.parent_id
  LEFT JOIN task_collection tc ON tc.id = tl.id
  LEFT JOIN delivery d ON d.id = t.delivery_id
  LEFT JOIN sylius_order o ON o.id = d.order_id
  LEFT JOIN task_package tp ON tp.task_id = t.id
  LEFT JOIN package p ON p.id = tp.package_id
  LEFT JOIN task_event task_done ON task_done.id = (SELECT tde.id FROM ${TaskDoneEvent.sql()} tde WHERE tde.task_id = t.id ORDER BY tde.created_at DESC LIMIT 1)
  LEFT JOIN task_event task_failed ON task_failed.id = (SELECT tfe.id FROM ${TaskFailedEvent.sql()} tfe WHERE tfe.task_id = t.id ORDER BY tfe.created_at DESC LIMIT 1)
  LEFT JOIN task_event task_finished ON task_finished.id = (SELECT tfe.id FROM ${TaskFinishedEvent.sql()} tfe WHERE tfe.task_id = t.id ORDER BY tfe.created_at DESC LIMIT 1)
  LEFT JOIN api_user u ON u.id = t.assigned_to
  LEFT JOIN organization org ON org.id = t.organization_id
  ORDER BY t.id, tci.position ASC
  `,

  dimensions: {
    taskId: {
      sql: `id`,
      type: `number`,
    },
    orderId: {
      sql: `order_id`,
      type: `number`,
    },
    orderNumber: {
      sql: `order_number`,
      type: `string`,
    },
    orderTotal: {
      sql: `order_total`,
      type: `number`,
    },
    orderFeeTotal: {
      sql: `order_fee_total`,
      type: `number`,
    },
    orderStripeFeeTotal: {
      sql: `order_stripe_fee_total`,
      type: `number`,
    },
    orderState: {
      sql: `order_state`,
      type: `number`,
    },
    taskType: {
      sql: `task_type`,
      type: `string`,
    },
    addressName: {
      sql: `address_name`,
      type: `string`,
    },
    addressStreetAddress: {
      sql: `address_street_address`,
      type: `string`,
    },
    addressGeo: {
      sql: `address_geo`,
      type: `string`,
    },
    addressDescription: {
      sql: `address_description`,
      type: `string`,
    },
    taskAfterDay: {
      sql: `to_char(${CUBE}.after, 'DD/MM/YYYY')`,
      type: `string`,
    },
    taskAfterTime: {
      sql: `${CUBE}.after::time`,
      type: `string`,
    },
    taskBeforeDay: {
      sql: `to_char(${CUBE}.before, 'DD/MM/YYYY')`,
      type: `string`,
    },
    taskBeforeTime: {
      sql: `${CUBE}.before::time`,
      type: `string`,
    },
    taskStatus: {
      sql: `status`,
      type: `string`,
    },
    taskComments: {
      sql: `comments`,
      type: `string`,
    },
    taskDoneNotes: {
      sql: `${CUBE}.task_done_data->>'notes'`,
      type: `string`,
    },
    taskFailedNotes: {
      sql: `${CUBE}.task_failed_data->>'notes'`,
      type: `string`,
    },
    taskFinishedAtDay: {
      sql: `to_char(${CUBE}.task_finished_at, 'DD/MM/YYYY')`,
      type: `string`,
    },
    taskFinishedAtTime: {
      sql: `${CUBE}.task_finished_at::time`,
      type: `string`,
    },
    taskCourier: {
      sql: `task_courier`,
      type: `string`,
    },
    taskTags: {
      sql: `tags`,
      type: `string`,
    },
    addressContactName: {
      sql: `address_contact_name`,
      type: `string`,
    },
    taskOrganizationName: {
      sql: `task_organization_name`,
      type: `string`,
    },
    taskPosition: {
      sql: `task_position`,
      type: `number`,
    },
    taskAfterDayTime: {
      sql: `${CUBE}.after::date`,
      type: `time`,
    },
    taskBeforeDayTime: {
      sql: `${CUBE}.before::date`,
      type: `time`,
    },
  },

  dataSource: `default`
});
