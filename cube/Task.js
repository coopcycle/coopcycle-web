const dateDiffInSeconds = (datetime) =>
  `((DATE_PART('day', ${datetime}) * 24
  + DATE_PART('hour', ${datetime})) * 60
  + DATE_PART('minute', ${datetime})) * 60
  + DATE_PART('second', ${datetime})`


const dateDiffInMinutes = (datetime) =>
  `(${dateDiffInSeconds(datetime)}) / 60`

const group = (value, bucketWidth) =>
  `floor(${value} / ${bucketWidth}) * ${bucketWidth} + ${bucketWidth / 2}`

const groupDiffInMinutes = (value) => group(value, 10)

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
        // drillMembers: [id],
      },
      countDone: {
        type: `count`,
        // drillMembers: [id],
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
  ),

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    //deprecated, kept for backward compatibility
    date: {
      sql: `done_before`,
      type: `time`
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
      sql: dateDiffInMinutes(`${CUBE.intervalEndAt} - ${CUBE.intervalStartAt}`),
      type: `number`
    },

    done: {
      sql: `${TaskDoneEvent.createdAt}`,
      type: `time`
    },

    // based on http://sqlines.com/postgresql/how-to/datediff
    minutesAfterStart: {
      sql: dateDiffInMinutes(`${CUBE.done} - ${CUBE.intervalStartAt}`),
      type: `number`
    },

    minutesBeforeEnd: {
      sql: dateDiffInMinutes(`${CUBE.intervalEndAt} - ${CUBE.done}`),
      type: `number`
    },

    notInIntervalMinutes: {
      type: `number`,
      case: {
        when: [
          { // early
            sql: `${CUBE.minutesAfterStart} < 0`,
            label: { sql: groupDiffInMinutes(`${CUBE.minutesAfterStart}`) },
          },
          { // on time
            sql: `${CUBE.minutesAfterStart} >= 0 AND ${CUBE.minutesBeforeEnd} >= 0`,
            label: { sql: `0` },
          },
          { // late
            sql: `${CUBE.minutesBeforeEnd} < 0`,
            label: { sql: `(${groupDiffInMinutes(`${CUBE.minutesBeforeEnd}`)}) * -1` },
          },
        ],
        else: { label: `0` },
      },
    },

    /**
     * width_bucket: https://www.postgresql.org/docs/current/functions-math.html
     * bucket width: 0,05
     * low: -4
     * high: 5
     * count of buckets: 180 = (high - low) / bucket width
     *
     * (1) width_bucket(..., -4, 5, 180):
     * maps [-4; 5] => [0; 180]
     * (<-4 => -4; >5 => 5)
     * (on time: [0;1] => [80; 100])
     *
     * (2) ... *0.05*100 - 450
     * maps [0; 180] => [-450; 450]
     * (on time: [80;100] => [-50%, 50%])
     */
    intervalDiff: {
      sql: `width_bucket((${CUBE.minutesAfterStart}) / (${CUBE.intervalMinutes}), -4, 5, 180)*0.05*100 - 450`,
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
