import { useState } from 'react'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Button } from '@/components/ui/button'
import { ChevronsUpDown, Check } from 'lucide-react'

const LANGUAGE_NAMES: Record<string, string> = {
  'en-US': 'English (US)',
  'en-GB': 'English (UK)',
  'en-AU': 'English (AU)',
  'en-CA': 'English (CA)',
  'en-IN': 'English (IN)',
  'en-SG': 'English (SG)',
  'es-MX': 'Spanish (Mexico)',
  'es-ES': 'Spanish (Spain)',
  'es-419': 'Spanish (LatAm)',
  'fr-FR': 'French',
  'fr-CA': 'French (Canada)',
  'de-DE': 'German',
  'pt-BR': 'Portuguese (Brazil)',
  'pt-PT': 'Portuguese (Portugal)',
  'zh-Hans': 'Chinese (Simplified)',
  'zh-Hant': 'Chinese (Traditional)',
  'zh-CN': 'Chinese (Simplified)',
  'zh-TW': 'Chinese (Traditional)',
  'zh-HK': 'Chinese (Hong Kong)',
  'nl-NL': 'Dutch',
  'nb': 'Norwegian',
  'nb-NO': 'Norwegian',
  'da': 'Danish',
  'da-DK': 'Danish',
  'fi': 'Finnish',
  'fi-FI': 'Finnish',
  'sv': 'Swedish',
  'cs': 'Czech',
  'cs-CZ': 'Czech',
  'el': 'Greek',
  'hu': 'Hungarian',
  'pl': 'Polish',
  'ro': 'Romanian',
  'sk': 'Slovak',
  'hr': 'Croatian',
  'uk': 'Ukrainian',
  'ru': 'Russian',
  'ar': 'Arabic',
  'he': 'Hebrew',
  'tr': 'Turkish',
  'tr-TR': 'Turkish',
  'ja': 'Japanese',
  'ko': 'Korean',
  'th': 'Thai',
  'vi': 'Vietnamese',
  'id': 'Indonesian',
  'ms': 'Malay',
  'hi': 'Hindi',
  'it': 'Italian',
  'ca': 'Catalan',
  'pt': 'Portuguese',
  'si': 'Sinhala',
  'fa': 'Persian',
  'fil': 'Filipino',
  'et': 'Estonian',
  'lv': 'Latvian',
  'lt': 'Lithuanian',
  'lo': 'Lao',
  'km': 'Khmer',
  'bn-BD': 'Bengali',
  'my-MM': 'Burmese',
  'ta': 'Tamil',
  'mr': 'Marathi',
  'te': 'Telugu',
  'kn': 'Kannada',
  'ml': 'Malayalam',
  'pa': 'Punjabi',
  'is': 'Icelandic',
  'yue': 'Cantonese',
}

function getLanguageName(code: string): string {
  return LANGUAGE_NAMES[code] ?? code
}

interface LanguageSelectProps {
  languages: string[]
  value: string
  onChange: (lang: string) => void
  className?: string
}

export default function LanguageSelect({ languages, value, onChange, className }: LanguageSelectProps) {
  const [open, setOpen] = useState(false)

  if (languages.length <= 1) return null

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger render={
        <Button variant="outline" role="combobox" aria-expanded={open} className={`justify-between ${className ?? 'w-[200px]'}`} />
      }>
        <span className="truncate">{getLanguageName(value)}</span>
        <ChevronsUpDown className="ml-1 h-4 w-4 shrink-0 opacity-50" />
      </PopoverTrigger>
      <PopoverContent className="w-[220px] p-0" align="start">
        <Command>
          <CommandInput placeholder="Search language..." />
          <CommandList>
            <CommandEmpty>No language found.</CommandEmpty>
            <CommandGroup>
              {languages.map((lang) => (
                <CommandItem
                  key={lang}
                  value={`${getLanguageName(lang)} ${lang}`}
                  onSelect={() => {
                    onChange(lang)
                    setOpen(false)
                  }}
                >
                  <Check className={`mr-2 h-4 w-4 ${value === lang ? 'opacity-100' : 'opacity-0'}`} />
                  {getLanguageName(lang)}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
