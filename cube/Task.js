const statuses = ['TODO', 'DOING', 'FAILED', 'DONE', 'CANCELLED'];

const createTotalByStatusMeasure = (status) => ({
  [`Total_${status}_tasks`]: {
    type: `count`,
    title: `Total ${status} tasks`,
    filters: [
      {
        sql: (CUBE) => `${CUBE}."status" = '${status}'`,
      },
    ],
  },
});

const createPercentageMeasure = (status) => ({
  [`Percentage_of_${status}`]: {
    type: `number`,
    format: `percent`,
    title: `Percentage of ${status} tasks`,
    sql: (CUBE) =>
      `ROUND(${CUBE[`Total_${status}_tasks`]}::numeric / ${CUBE.count}::numeric * 100.0, 2)`,
  },
});


cube(`Task`, {
  sql: `SELECT id, type, done_after AS after, done_before AS before, status FROM public.task`,

  joins: {

  },

  measures: Object.assign(
    {
      count: {
        type: `count`,
        drillMembers: [id]
      },
    },
    statuses.reduce(
      (all, status) => ({
        ...all,
        ...createTotalByStatusMeasure(status),
        ...createPercentageMeasure(status),
      }),
      {}
    )
  ),

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    date: {
      sql: `before`,
      type: `time`
    },

    status: {
      sql: `status`,
      type: `string`
    },

  },

  dataSource: `default`
});
