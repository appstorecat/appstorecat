import type { FolderColorEnum } from '@/api/models/folderColorEnum'

export const FOLDER_COLORS = {
  slate:  { dot: 'bg-slate-500',  label: 'Slate' },
  red:    { dot: 'bg-red-500',    label: 'Red' },
  orange: { dot: 'bg-orange-500', label: 'Orange' },
  amber:  { dot: 'bg-amber-500',  label: 'Amber' },
  yellow: { dot: 'bg-yellow-500', label: 'Yellow' },
  green:  { dot: 'bg-green-500',  label: 'Green' },
  teal:   { dot: 'bg-teal-500',   label: 'Teal' },
  blue:   { dot: 'bg-blue-500',   label: 'Blue' },
  purple: { dot: 'bg-purple-500', label: 'Purple' },
  pink:   { dot: 'bg-pink-500',   label: 'Pink' },
} as const

export type FolderColorKey = keyof typeof FOLDER_COLORS

export const FOLDER_COLOR_KEYS = Object.keys(FOLDER_COLORS) as FolderColorKey[]

export function folderDotClass(color: FolderColorEnum | string | null | undefined): string {
  if (color && color in FOLDER_COLORS) {
    return FOLDER_COLORS[color as FolderColorKey].dot
  }
  return FOLDER_COLORS.slate.dot
}
