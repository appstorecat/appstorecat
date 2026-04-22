import type { ReactNode } from 'react'
import { Search } from 'lucide-react'
import { Input } from '@/components/ui/input'

interface FilterBarProps {
  children: ReactNode
  className?: string
}

interface SearchProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  className?: string
}

interface ControlsProps {
  children: ReactNode
  className?: string
}

function FilterBar({ children, className }: FilterBarProps) {
  return (
    <div className={`flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center ${className ?? ''}`}>
      {children}
    </div>
  )
}

function FilterBarSearch({ value, onChange, placeholder, className }: SearchProps) {
  return (
    <div className={`relative w-full sm:w-auto sm:min-w-[240px] sm:flex-1 ${className ?? ''}`}>
      <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="pl-9"
      />
    </div>
  )
}

function FilterBarControls({ children, className }: ControlsProps) {
  return (
    <div
      className={`-mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:gap-3 sm:overflow-visible sm:px-0 sm:pb-0 [&>*]:shrink-0 ${className ?? ''}`}
    >
      {children}
    </div>
  )
}

FilterBar.Search = FilterBarSearch
FilterBar.Controls = FilterBarControls

export default FilterBar
