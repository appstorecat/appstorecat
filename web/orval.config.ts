import { defineConfig } from 'orval'

export default defineConfig({
  appstorecat: {
    input: '../server/storage/api-docs/api-docs.json',
    output: {
      target: './src/api/endpoints',
      schemas: './src/api/models',
      client: 'react-query',
      mode: 'tags-split',
      baseUrl: '/api/v1',
    },
  },
})
