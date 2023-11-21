// https://cube.dev/docs/multitenancy-setup#multiple-db-instances-with-same-schema

const PostgresDriver = require('@cubejs-backend/postgres-driver');
const axios = require('axios')

module.exports = {
  contextToAppId: ({ securityContext }) =>
    `CUBEJS_APP_${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
  driverFactory: ({ securityContext }) =>
    new PostgresDriver({
      database: `${securityContext && securityContext.database ? securityContext.database : 'coopcycle'}`,
    }),
  // https://cube.dev/docs/reference/configuration/config#repository_factory
  repositoryFactory: ({ securityContext }) => {

    return {
      dataSchemaFiles: async () => {

        const schemaFiles = []

        const response = await axios.get(`${securityContext.base_url}/api/cube_data_schema_files`)

        await Promise.all(response.data['hydra:member'].map(async (item) => {
          const itemResponse = await axios.get(`${securityContext.base_url}${item['@id']}`)
          schemaFiles.push(itemResponse.data)
        }));

        return await Promise.resolve(schemaFiles.map((schemaFile) => {
          return {
            fileName: schemaFile.filename,
            content: schemaFile.contents
          }
        }))
      }
    };
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
