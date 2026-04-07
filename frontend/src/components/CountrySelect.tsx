import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import axios from '@/lib/axios'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Button } from '@/components/ui/button'
import { ChevronsUpDown, Check } from 'lucide-react'

export interface Country {
  code: string
  name: string
  emoji: string
  ios_languages: string[] | null
  android_languages: string[] | null
}

export function useCountries() {
  return useQuery<Country[]>({
    queryKey: ['countries'],
    queryFn: () => axios.get('/countries').then((r) => r.data),
    staleTime: Infinity,
  })
}

interface CountrySelectProps {
  value: string
  onChange: (code: string) => void
  className?: string
}

function flagUrl(code: string): string {
  return `https://flagcdn.com/w40/${code}.png`
}

export default function CountrySelect({ value, onChange, className }: CountrySelectProps) {
  const [open, setOpen] = useState(false)
  const { data: countries } = useCountries()
  const selected = countries?.find((c) => c.code === value)

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger render={
        <Button variant="outline" role="combobox" aria-expanded={open} className={`justify-between ${className ?? 'w-[200px]'}`} />
      }>
        <span className="flex items-center gap-2 truncate">
          <img src={flagUrl(value)} alt="" className="h-3.5 w-5 shrink-0 rounded-[2px] object-cover" />
          {selected?.name ?? value.toUpperCase()}
        </span>
        <ChevronsUpDown className="ml-1 h-4 w-4 shrink-0 opacity-50" />
      </PopoverTrigger>
      <PopoverContent className="w-[240px] p-0" align="start">
        <Command>
          <CommandInput placeholder="Search country..." />
          <CommandList>
            <CommandEmpty>No country found.</CommandEmpty>
            <CommandGroup>
              {countries?.map((c) => (
                <CommandItem
                  key={c.code}
                  value={`${c.name} ${c.code}`}
                  onSelect={() => {
                    onChange(c.code)
                    setOpen(false)
                  }}
                >
                  <Check className={`mr-2 h-4 w-4 ${value === c.code ? 'opacity-100' : 'opacity-0'}`} />
                  <img src={flagUrl(c.code)} alt="" className="mr-2 h-3.5 w-5 shrink-0 rounded-[2px] object-cover" />
                  {c.name}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
