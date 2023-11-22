// https://cube.dev/docs/multitenancy-setup#multiple-db-instances-with-same-schema

const PostgresDriver = require('@cubejs-backend/postgres-driver');
const axios = require('axios')

const schemaFilesByHost = {}

module.exports = {
  contextToAppId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
  contextToOrchestratorId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
  driverFactory: ({ securityContext }) =>
    new PostgresDriver({
      database: `${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
    }),
  schemaVersion: ({ securityContext }) => {
    return 'v1';
  },
  // https://cube.dev/docs/reference/configuration/config#repository_factory
  repositoryFactory: ({ securityContext }) => {

    // TODO Use CUBEJS_DEV_MODE or NODE_ENV for http/https
    // console.log(process.env)

    return {
      dataSchemaFiles: async () => {

        if (schemaFilesByHost.hasOwnProperty(securityContext.base_url)) {
          return Promise.resolve(
            schemaFilesByHost[securityContext.base_url]
          )
        }

        const schemaFiles = []

        const response = await axios.get(`${securityContext.base_url}/api/cube_data_schema_files`)

        await Promise.all(response.data['hydra:member'].map(async (item) => {
          const itemResponse = await axios.get(`${securityContext.base_url}${item['@id']}`)
          schemaFiles.push(itemResponse.data)
        }));

        const files = schemaFiles.map((schemaFile) => {
          return {
            fileName: schemaFile.filename,
            content: schemaFile.contents
          }
        })

        schemaFilesByHost[securityContext.base_url] = files

        return Promise.resolve(files)
      }
    }
  },
  // Leaving scheduled_refresh_contexts unconfigured will lead to issues where the security context will be undefined.
  // This is because there is no way for Cube to know how to generate a context without the required input.
  // https://cube.dev/docs/reference/configuration/config#scheduled_refresh_contexts
  scheduledRefreshContexts: async () => [
    {
      securityContext: {
        database: 'coopcycle',
        base_uri: 'http://nginx'
      },
    },
  ],
};
