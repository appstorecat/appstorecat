import type { AxiosRequestConfig } from 'axios'

import axios from '@/lib/axios'

/**
 * Orval custom mutator. Routes every generated API call through the shared
 * axios instance so that the Bearer token interceptor, 401 redirect, and
 * `baseURL` configuration in `@/lib/axios` apply uniformly.
 *
 * Orval passes the request shape as the first argument (`{ url, method,
 * params, data, headers, signal, ... }`) and optional per-call overrides as
 * the second. We return the unwrapped response body (`Promise<T>`), which is
 * what the generated react-query hooks expect.
 */
export const orvalMutator = <T>(
  config: AxiosRequestConfig,
  options?: AxiosRequestConfig,
): Promise<T> => {
  const source = axios.CancelToken.source()

  const promise = axios({
    ...config,
    ...options,
    cancelToken: source.token,
  }).then(({ data }) => data as T)

  // Allow react-query to cancel in-flight requests on unmount / key change.
  ;(promise as Promise<T> & { cancel?: () => void }).cancel = () => {
    source.cancel('Query was cancelled')
  }

  return promise
}

export default orvalMutator
