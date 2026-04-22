import { useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'

export type ChangesPlatform = 'all' | 'ios' | 'android'

export type ChangesField =
  | 'all'
  | 'title'
  | 'subtitle'
  | 'description'
  | 'whats_new'
  | 'screenshots'
  | 'locale_added'
  | 'locale_removed'

const FIELD_VALUES: ChangesField[] = [
  'all',
  'title',
  'subtitle',
  'description',
  'whats_new',
  'screenshots',
  'locale_added',
  'locale_removed',
]

function parsePlatform(value: string | null): ChangesPlatform {
  return value === 'ios' || value === 'android' ? value : 'all'
}

function parseField(value: string | null): ChangesField {
  return FIELD_VALUES.includes(value as ChangesField) ? (value as ChangesField) : 'all'
}

export interface ChangesFilters {
  platform: ChangesPlatform
  field: ChangesField
  search: string
  setPlatform: (value: ChangesPlatform) => void
  setField: (value: ChangesField) => void
  setSearch: (value: string) => void
  clearAll: () => void
  hasAny: boolean
}

export function useChangesFilters(): ChangesFilters {
  const [searchParams, setSearchParams] = useSearchParams()

  const platform = parsePlatform(searchParams.get('platform'))
  const field = parseField(searchParams.get('field'))
  const search = searchParams.get('search') ?? ''

  const update = useCallback(
    (key: string, value: string | null) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          if (value === null || value === '') {
            next.delete(key)
          } else {
            next.set(key, value)
          }
          return next
        },
        { replace: true },
      )
    },
    [setSearchParams],
  )

  const setPlatform = useCallback(
    (value: ChangesPlatform) => update('platform', value === 'all' ? null : value),
    [update],
  )

  const setField = useCallback(
    (value: ChangesField) => update('field', value === 'all' ? null : value),
    [update],
  )

  const setSearch = useCallback((value: string) => update('search', value || null), [update])

  const clearAll = useCallback(() => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev)
        next.delete('platform')
        next.delete('field')
        next.delete('search')
        return next
      },
      { replace: true },
    )
  }, [setSearchParams])

  const hasAny = platform !== 'all' || field !== 'all' || search.length > 0

  return {
    platform,
    field,
    search,
    setPlatform,
    setField,
    setSearch,
    clearAll,
    hasAny,
  }
}
