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
  // Orval passes an AbortSignal in `config.signal` when react-query wants to
  // cancel — axios forwards it through natively, no manual cancel plumbing.
  return axios({ ...config, ...options }).then(({ data }) => data as T)
}

export default orvalMutator
