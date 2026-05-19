import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  useStoreFolder,
  useUpdateFolder,
  getListFoldersQueryKey,
} from '@/api/endpoints/folders/folders'
import type { FolderResource } from '@/api/models/folderResource'
import { FolderColorEnum } from '@/api/models/folderColorEnum'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { FOLDER_COLORS, FOLDER_COLOR_KEYS, type FolderColorKey } from '@/lib/folderColors'
import { cn } from '@/lib/utils'
import { Check } from 'lucide-react'

interface FolderDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  folder?: FolderResource | null
}

const MAX_NAME_LENGTH = 60

export default function FolderDialog({ open, onOpenChange, folder }: FolderDialogProps) {
  const queryClient = useQueryClient()
  const isEditing = Boolean(folder)

  const [name, setName] = useState('')
  const [color, setColor] = useState<FolderColorKey>('slate')
  const [error, setError] = useState('')

  useEffect(() => {
    if (open) {
      setName(folder?.name ?? '')
      setColor((folder?.color as FolderColorKey) ?? 'slate')
      setError('')
    }
  }, [open, folder])

  const storeFolder = useStoreFolder({
    mutation: {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListFoldersQueryKey() })
        onOpenChange(false)
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setError(msg || 'Failed to create folder.')
      },
    },
  })

  const updateFolder = useUpdateFolder({
    mutation: {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListFoldersQueryKey() })
        onOpenChange(false)
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setError(msg || 'Failed to update folder.')
      },
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    const trimmedName = name.trim()
    if (!trimmedName) {
      setError('Name is required.')
      return
    }
    if (trimmedName.length > MAX_NAME_LENGTH) {
      setError(`Name must be at most ${MAX_NAME_LENGTH} characters.`)
      return
    }

    if (isEditing && folder) {
      updateFolder.mutate({
        folder: folder.id,
        data: { name: trimmedName, color: FolderColorEnum[color] },
      })
    } else {
      storeFolder.mutate({
        data: { name: trimmedName, color: FolderColorEnum[color] },
      })
    }
  }

  const isSubmitting = storeFolder.isPending || updateFolder.isPending

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? 'Edit folder' : 'New folder'}</DialogTitle>
          <DialogDescription>
            {isEditing ? 'Update folder details.' : 'Create a folder to organize tracked apps.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="folder-name">Name</Label>
            <Input
              id="folder-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              maxLength={MAX_NAME_LENGTH}
              autoFocus
              placeholder="e.g. Competitors"
            />
            <p className="text-xs text-muted-foreground">
              {name.length}/{MAX_NAME_LENGTH}
            </p>
          </div>

          <div className="flex flex-col gap-1.5">
            <Label>Color</Label>
            <div className="grid grid-cols-5 gap-2">
              {FOLDER_COLOR_KEYS.map((key) => {
                const isActive = color === key
                return (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setColor(key)}
                    aria-pressed={isActive}
                    aria-label={FOLDER_COLORS[key].label}
                    title={FOLDER_COLORS[key].label}
                    className={cn(
                      'flex h-9 w-full items-center justify-center rounded-md border transition-colors',
                      isActive ? 'border-foreground' : 'border-transparent hover:border-border',
                    )}
                  >
                    <span
                      className={cn(
                        'flex h-5 w-5 items-center justify-center rounded-full',
                        FOLDER_COLORS[key].dot,
                      )}
                    >
                      {isActive ? <Check className="h-3 w-3 text-white" /> : null}
                    </span>
                  </button>
                )
              })}
            </div>
          </div>

          {error ? <p className="text-sm text-destructive">{error}</p> : null}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting || !name.trim()}>
              {isSubmitting ? 'Saving…' : isEditing ? 'Save' : 'Create'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
