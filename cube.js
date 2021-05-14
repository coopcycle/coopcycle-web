// https://cube.dev/docs/multitenancy-setup#multiple-db-instances-with-same-schema

const PostgresDriver = require('@cubejs-backend/postgres-driver');

module.exports = {
  contextToAppId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext.database}`,
  driverFactory: ({ securityContext }) =>
    new PostgresDriver({
      database: `${securityContext.database}`,
    }),
  // https://cube.dev/docs/config#options-reference-scheduled-refresh-contexts
  scheduledRefreshContexts: async () => [
    {
      securityContext: {
        database: 'coopcycle',
      },
    },
  ],
};
