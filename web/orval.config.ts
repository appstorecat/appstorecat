import { defineConfig } from 'orval'

export default defineConfig({
  appstorecat: {
    input: '../server/storage/api-docs/api-docs.json',
    output: {
      target: './src/api/endpoints',
      schemas: './src/api/models',
      client: 'react-query',
      httpClient: 'axios',
      mode: 'tags-split',
      override: {
        mutator: {
          path: './src/lib/orval-mutator.ts',
          name: 'orvalMutator',
        },
      },
    },
  },
})
