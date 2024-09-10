// https://cube.dev/docs/multitenancy-setup#multiple-db-instances-with-same-schema

const PostgresDriver = require('@cubejs-backend/postgres-driver');
const DuckDbDriver = require('@cubejs-backend/duckdb-driver');

module.exports = {
  contextToAppId: ({ securityContext: {database, instance, year, month} }) => ['CUBEJS_APP', database, instance, year, month].filter(t => t).join('_'),
  contextToOrchestratorId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
  driverFactory: ({ securityContext, dataSource }) => {

    if (dataSource === 'duckdb') {
      return new DuckDbDriver({})
    }

    return new PostgresDriver({
      database: `${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
    })
  },


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
