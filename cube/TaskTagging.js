cube(`TaskTagging`, {
  sql: `
    SELECT
      tagging.id AS id,
      tagging.resource_id AS resource_id,
      tag.id AS tag_id,
      tag.name AS tag_name
    FROM public.tagging tagging
    JOIN public.tag tag ON tag.id = tagging.tag_id
    WHERE resource_class='AppBundle\\Entity\\Task'
  `,

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    tag_id: {
      sql: `tag_id`,
      type: `number`
    },

    resource_id: {
      sql: `resource_id`,
      type: `number`
    },

    tag_name: {
      sql: `tag_name`,
      type: `string`
    }

  },

  dataSource: `default`
});
