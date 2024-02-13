// https://cube.dev/docs/multitenancy-setup#multiple-db-instances-with-same-schema

const PostgresDriver = require('@cubejs-backend/postgres-driver');

module.exports = {
  contextToAppId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
  contextToOrchestratorId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
  driverFactory: ({ securityContext }) =>
    new PostgresDriver({
      database: `${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
    }),
  // https://cube.dev/docs/config#options-reference-scheduled-refresh-contexts
  scheduledRefreshContexts: () => [
    {
      securityContext: {
        database: 'coopcycle',
        base_url: 'http://nginx'
      },
    },
  ],
};
