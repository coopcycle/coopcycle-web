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
  sql: `SELECT id, type, done_after, done_before, status FROM public.task`,

  joins: {
    TaskDoneEvent: {
      relationship: `hasOne`,
      sql: `${CUBE.id} = ${TaskDoneEvent}.task_id`,
    },
  },

  measures: Object.assign(
    {
      count: {
        type: `count`,
        drillMembers: [id]
      },
      countDoneTooEarly: {
        sql: `id`,
        type: `count`,
        filters: [{ sql: `${CUBE.minutesAfterStart} < 0` }],
      },
      countDoneTooLate: {
        sql: `id`,
        type: `count`,
        filters: [{ sql: `${CUBE.minutesBeforeEnd} < 0` }],
      },
      countDoneOnTime: {
        sql: `id`,
        type: `count`,
        filters: [{ sql: `${CUBE.minutesAfterStart} > 0 AND ${CUBE.minutesBeforeEnd} > 0` }],
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

    intervalStartAt: {
      sql: `done_after`,
      type: `time`
    },

    intervalEndAt: {
      sql: `done_before`,
      type: `time`
    },

    done: {
      sql: `${TaskDoneEvent.createdAt}`,
      type: `time`
    },

    minutesAfterStart: {
      sql: `DATE_PART('hour', ${CUBE.done} - ${CUBE.intervalStartAt}) * 60
          + DATE_PART('minute', ${CUBE.done} - ${CUBE.intervalStartAt})`,
      type: `number`
    },

    minutesBeforeEnd: {
      sql: `DATE_PART('hour', ${CUBE.intervalEndAt} - ${CUBE.done}) * 60
          + DATE_PART('minute', ${CUBE.intervalEndAt} - ${CUBE.done})`,
      type: `number`
    },

    status: {
      sql: `status`,
      type: `string`
    },

  },

  dataSource: `default`
});
