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
      sql: `${CUBE.status} = 'DONE' AND ${CUBE.id} = ${TaskDoneEvent}.task_id`,
    },
  },

  measures: Object.assign(
    {
      count: {
        type: `count`,
        drillMembers: [id],
      },
      countDone: {
        type: `count`,
        drillMembers: [id],
        filters: [{ sql: `${CUBE.status} = 'DONE'` }],
      },
      countTooEarly: {
        sql: `id`,
        type: `count`,
        filters: [{ sql: `${CUBE.minutesAfterStart} < 0` }],
      },
      averageTooEarly: {
        sql: `${CUBE.minutesAfterStart}`,
        type: `avg`,
        filters: [{ sql: `${CUBE.minutesAfterStart} < 0` }],
      },
      countTooLate: {
        sql: `id`,
        type: `count`,
        filters: [{ sql: `${CUBE.minutesBeforeEnd} < 0` }],
      },
      averageTooLate: {
        sql: `${CUBE.minutesBeforeEnd}`,
        type: `avg`,
        filters: [{ sql: `${CUBE.minutesBeforeEnd} < 0` }],
      },
      countOnTime: {
        sql: `id`,
        type: `count`,
        filters: [{ sql: `${CUBE.minutesAfterStart} >= 0 AND ${CUBE.minutesBeforeEnd} >= 0` }],
      },
      percentageTooEarly: {
        type: `number`,
        format: `percent`,
        sql: `ROUND(${CUBE.countTooEarly}::numeric / ${CUBE.countDone}::numeric * 100.0, 2)`,
        filters: [{ sql: `${CUBE.countDone} > 0` }],
      },
      percentageTooLate: {
        type: `number`,
        format: `percent`,
        sql: `ROUND(${CUBE.countTooLate}::numeric / ${CUBE.countDone}::numeric * 100.0, 2)`,
        filters: [{ sql: `${CUBE.countDone} > 0` }],
      },
      percentageOnTime: {
        type: `number`,
        format: `percent`,
        sql: `ROUND(${CUBE.countOnTime}::numeric / ${CUBE.countDone}::numeric * 100.0, 2)`,
        filters: [{ sql: `${CUBE.countDone} > 0` }],
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

    intervalMinutes: {
      sql: `DATE_PART('day', ${CUBE.intervalEndAt} - ${CUBE.intervalStartAt}) * 24 * 60
          + DATE_PART('hour', ${CUBE.intervalEndAt} - ${CUBE.intervalStartAt}) * 60
          + DATE_PART('minute', ${CUBE.intervalEndAt} - ${CUBE.intervalStartAt})`,
      type: `number`
    },

    done: {
      sql: `${TaskDoneEvent.createdAt}`,
      type: `time`
    },

    // based on http://sqlines.com/postgresql/how-to/datediff
    minutesAfterStart: {
      sql: `DATE_PART('day', ${CUBE.done} - ${CUBE.intervalStartAt}) * 24 * 60
          + DATE_PART('hour', ${CUBE.done} - ${CUBE.intervalStartAt}) * 60
          + DATE_PART('minute', ${CUBE.done} - ${CUBE.intervalStartAt})`,
      type: `number`
    },

    minutesBeforeEnd: {
      sql: `DATE_PART('day', ${CUBE.intervalEndAt} - ${CUBE.done}) * 24 * 60
          + DATE_PART('hour', ${CUBE.intervalEndAt} - ${CUBE.done}) * 60
          + DATE_PART('minute', ${CUBE.intervalEndAt} - ${CUBE.done})`,
      type: `number`
    },

    notInIntervalMinutes: {
      type: `number`,
      case: {
        when: [
          {
            sql: `${CUBE.minutesAfterStart} < 0`,
            label: { sql: `${CUBE.minutesAfterStart}` },
          },
          {
            sql: `${CUBE.minutesAfterStart} >= 0 AND ${CUBE.minutesBeforeEnd} >= 0`,
            label: { sql: `0` },
          },
          {
            sql: `${CUBE.minutesBeforeEnd} < 0`,
            label: { sql: `(${CUBE.minutesBeforeEnd}) * -1` },
          },
        ],
        else: { label: `0` },
      },
    },

    intervalDiffTemp: {
      sql: `cast(((${CUBE.minutesAfterStart}) / (${CUBE.intervalMinutes}) - 0.5) * 100 as bigint)`,
      type: `number`
    },

    intervalDiff: {
      sql: `width_bucket((${CUBE.minutesAfterStart}) / (${CUBE.intervalMinutes}), -2, 3, 100)*10 - 550`,
      type: `number`
    },

    status: {
      sql: `status`,
      type: `string`
    },

    type: {
      sql: `type`,
      type: `string`
    },
  },

  segments: {
    pickup: {
      sql: `${CUBE.type} = 'PICKUP'`,
    },
    dropoff: {
      sql: `${CUBE.type} = 'DROPOFF'`,
    },
  },

  dataSource: `default`
});
