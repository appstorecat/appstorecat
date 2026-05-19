import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  useListFolders,
  useDestroyFolder,
  getListFoldersQueryKey,
} from '@/api/endpoints/folders/folders'
import { useMoveAppToFolder } from '@/api/endpoints/apps/apps'
import type { FolderResource } from '@/api/models/folderResource'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { MoreVertical, Plus, Inbox, LayoutGrid, Pencil, Trash2 } from 'lucide-react'
import { folderDotClass } from '@/lib/folderColors'
import { cn } from '@/lib/utils'
import FolderDialog from './FolderDialog'

interface FolderSidebarProps {
  activeFolderId: string | null
  onSelect: (folderId: string | null) => void
}

export default function FolderSidebar({ activeFolderId, onSelect }: FolderSidebarProps) {
  const queryClient = useQueryClient()
  const { data: folders, isPending } = useListFolders()

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingFolder, setEditingFolder] = useState<FolderResource | null>(null)
  const [folderToDelete, setFolderToDelete] = useState<FolderResource | null>(null)
  const [deleteError, setDeleteError] = useState('')
  const [activeDropTarget, setActiveDropTarget] = useState<string | null>(null)

  const moveAppMutation = useMoveAppToFolder()

  const invalidateAfterMove = () => {
    queryClient.invalidateQueries({ queryKey: getListFoldersQueryKey() })
    queryClient.invalidateQueries({ queryKey: ['/apps'] })
  }

  const handleDrop = (e: React.DragEvent, folderId: number | null) => {
    e.preventDefault()
    setActiveDropTarget(null)
    const externalId = e.dataTransfer.getData('app/external-id')
    const platform = e.dataTransfer.getData('app/platform') as 'ios' | 'android'
    if (!externalId || (platform !== 'ios' && platform !== 'android')) return
    moveAppMutation.mutate(
      { platform, externalId, data: { folder_id: folderId } },
      { onSuccess: invalidateAfterMove },
    )
  }

  const dropHandlers = (key: string, folderId: number | null) => ({
    onDragOver: (e: React.DragEvent) => {
      if (!e.dataTransfer.types.includes('app/external-id')) return
      e.preventDefault()
      e.dataTransfer.dropEffect = 'move'
    },
    onDragEnter: (e: React.DragEvent) => {
      if (!e.dataTransfer.types.includes('app/external-id')) return
      setActiveDropTarget(key)
    },
    onDragLeave: (e: React.DragEvent) => {
      // Only clear when leaving the element (not crossing into children)
      if (e.currentTarget.contains(e.relatedTarget as Node)) return
      setActiveDropTarget((prev) => (prev === key ? null : prev))
    },
    onDrop: (e: React.DragEvent) => handleDrop(e, folderId),
  })

  const destroyFolder = useDestroyFolder({
    mutation: {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListFoldersQueryKey() })
        if (folderToDelete && String(folderToDelete.id) === activeFolderId) {
          onSelect(null)
        }
        setFolderToDelete(null)
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setDeleteError(msg || 'Failed to delete folder.')
      },
    },
  })

  const openCreate = () => {
    setEditingFolder(null)
    setDialogOpen(true)
  }

  const openEdit = (folder: FolderResource) => {
    setEditingFolder(folder)
    setDialogOpen(true)
  }

  const confirmDelete = () => {
    if (!folderToDelete) return
    setDeleteError('')
    destroyFolder.mutate({ folder: folderToDelete.id })
  }

  return (
    <aside className="w-56 shrink-0 sticky top-14 self-start">
      <div className="flex flex-col gap-1 rounded-xl border bg-card p-2">
        <SidebarLink
          active={activeFolderId === null}
          onClick={() => onSelect(null)}
          icon={<LayoutGrid className="h-4 w-4 text-muted-foreground" />}
          label="All apps"
          isDropActive={activeDropTarget === 'all'}
          dropHandlers={dropHandlers('all', null)}
        />

        <div className="my-1 h-px bg-border" />

        {isPending ? (
          <div className="flex flex-col gap-1 px-1">
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
          </div>
        ) : folders && folders.length > 0 ? (
          folders.map((folder) => {
            const isActive = activeFolderId === String(folder.id)
            const dropKey = `folder-${folder.id}`
            const isDropActive = activeDropTarget === dropKey
            return (
              <div
                key={folder.id}
                {...dropHandlers(dropKey, folder.id)}
                className={cn(
                  'group flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
                  isActive ? 'bg-accent text-accent-foreground' : 'hover:bg-muted',
                  isDropActive && 'bg-accent/60 ring-2 ring-primary/40',
                )}
              >
                <button
                  type="button"
                  onClick={() => onSelect(String(folder.id))}
                  className="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-left"
                >
                  <span
                    className={cn(
                      'h-2.5 w-2.5 shrink-0 rounded-full',
                      folderDotClass(folder.color),
                    )}
                  />
                  <span className="truncate flex-1">{folder.name}</span>
                  <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                    {folder.apps_count}
                  </span>
                </button>

                <DropdownMenu>
                  <DropdownMenuTrigger
                    render={
                      <Button
                        variant="ghost"
                        size="icon-xs"
                        className="opacity-0 group-hover:opacity-100 data-[popup-open]:opacity-100"
                        aria-label={`${folder.name} actions`}
                      />
                    }
                  >
                    <MoreVertical />
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => openEdit(folder)}>
                      <Pencil className="h-4 w-4" />
                      Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      variant="destructive"
                      onClick={() => {
                        setDeleteError('')
                        setFolderToDelete(folder)
                      }}
                    >
                      <Trash2 className="h-4 w-4" />
                      Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            )
          })
        ) : (
          <p className="px-2 py-1.5 text-xs text-muted-foreground">No folders yet.</p>
        )}

        <div className="my-1 h-px bg-border" />

        <SidebarLink
          active={activeFolderId === 'unassigned'}
          onClick={() => onSelect('unassigned')}
          icon={<Inbox className="h-4 w-4 text-muted-foreground" />}
          label="Unassigned"
          isDropActive={activeDropTarget === 'unassigned'}
          dropHandlers={dropHandlers('unassigned', null)}
        />

        <Button
          variant="ghost"
          size="sm"
          onClick={openCreate}
          className="mt-1 w-full justify-start text-muted-foreground"
        >
          <Plus className="h-4 w-4" />
          New folder
        </Button>
      </div>

      <FolderDialog
        open={dialogOpen}
        onOpenChange={(open) => {
          setDialogOpen(open)
          if (!open) setEditingFolder(null)
        }}
        folder={editingFolder}
      />

      <AlertDialog
        open={folderToDelete !== null}
        onOpenChange={(open) => {
          if (!open) {
            setFolderToDelete(null)
            setDeleteError('')
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete folder?</AlertDialogTitle>
            <AlertDialogDescription>
              {folderToDelete ? (
                <>
                  Delete <strong>{folderToDelete.name}</strong>? Tracked apps inside will stay
                  tracked but become unassigned.
                </>
              ) : null}
            </AlertDialogDescription>
          </AlertDialogHeader>
          {deleteError ? <p className="text-sm text-destructive">{deleteError}</p> : null}
          <AlertDialogFooter>
            <AlertDialogCancel disabled={destroyFolder.isPending}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={confirmDelete}
              disabled={destroyFolder.isPending}
            >
              {destroyFolder.isPending ? 'Deleting…' : 'Delete'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </aside>
  )
}

interface SidebarLinkProps {
  active: boolean
  onClick: () => void
  icon: React.ReactNode
  label: string
  isDropActive?: boolean
  dropHandlers?: {
    onDragOver: (e: React.DragEvent) => void
    onDragEnter: (e: React.DragEvent) => void
    onDragLeave: (e: React.DragEvent) => void
    onDrop: (e: React.DragEvent) => void
  }
}

function SidebarLink({ active, onClick, icon, label, isDropActive, dropHandlers }: SidebarLinkProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      {...(dropHandlers ?? {})}
      className={cn(
        'flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
        active ? 'bg-accent text-accent-foreground' : 'hover:bg-muted',
        isDropActive && 'bg-accent/60 ring-2 ring-primary/40',
      )}
    >
      {icon}
      <span className="flex-1 text-left">{label}</span>
    </button>
  )
}

