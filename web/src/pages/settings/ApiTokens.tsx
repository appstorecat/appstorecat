import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  useListApiTokens,
  useCreateApiToken,
  useRevokeApiToken,
  getListApiTokensQueryKey,
} from '@/api/endpoints/account/account'
import type { ApiTokenResource } from '@/api/models/apiTokenResource'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Copy, Trash2, Plus, Key } from 'lucide-react'

function formatDate(iso: string | null | undefined) {
  if (!iso) return 'Never'
  return new Date(iso).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export default function ApiTokens() {
  const queryClient = useQueryClient()
  const { data: tokens = [], isLoading: loading } = useListApiTokens()

  const [name, setName] = useState('')
  const [error, setError] = useState('')
  const [newToken, setNewToken] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [revokeError, setRevokeError] = useState('')
  const [tokenToRevoke, setTokenToRevoke] = useState<ApiTokenResource | null>(null)

  const createToken = useCreateApiToken({
    mutation: {
      onSuccess: (data) => {
        setNewToken(data.plain_text_token)
        setName('')
        queryClient.invalidateQueries({ queryKey: getListApiTokensQueryKey() })
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setError(msg || 'Failed to create token.')
      },
    },
  })

  const revokeToken = useRevokeApiToken({
    mutation: {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListApiTokensQueryKey() })
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setRevokeError(msg || 'Failed to revoke token.')
      },
    },
  })

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    createToken.mutate({ data: { name } })
  }

  const handleRevoke = () => {
    if (!tokenToRevoke?.id) return
    setRevokeError('')
    const id = tokenToRevoke.id
    revokeToken.mutate(
      { tokenId: id },
      {
        onSettled: () => setTokenToRevoke(null),
      },
    )
  }

  const handleCopy = async () => {
    if (!newToken) return
    await navigator.clipboard.writeText(newToken)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      <h1 className="text-2xl font-bold">API Tokens</h1>
      <div className="mx-auto w-full max-w-2xl space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Create Token</CardTitle>
            <CardDescription>
              Generate an API token to use with the AppStoreCat MCP server or API integrations.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleCreate} className="flex items-end gap-3">
              {error && <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive w-full">{error}</div>}
              <div className="grid flex-1 gap-2">
                <Label htmlFor="token_name">Token Name</Label>
                <Input
                  id="token_name"
                  placeholder="e.g. My MCP Token"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  maxLength={255}
                />
              </div>
              <Button type="submit" disabled={createToken.isPending || !name.trim()}>
                <Plus className="mr-2 h-4 w-4" />
                {createToken.isPending ? 'Creating...' : 'Create'}
              </Button>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Your Tokens</CardTitle>
            <CardDescription>
              Manage your existing API tokens. Tokens are used to authenticate MCP and API requests.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {revokeError && <div className="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">{revokeError}</div>}
            {loading ? (
              <div className="flex justify-center py-8">
                <div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              </div>
            ) : tokens.length === 0 ? (
              <div className="flex flex-col items-center gap-2 py-8 text-muted-foreground">
                <Key className="h-8 w-8" />
                <p className="text-sm">No API tokens yet.</p>
              </div>
            ) : (
              <div className="divide-y">
                {tokens.map((token) => (
                  <div key={token.id} className="flex items-center justify-between py-3">
                    <div className="space-y-1">
                      <p className="text-sm font-medium">{token.name}</p>
                      <p className="text-xs text-muted-foreground">
                        Created {formatDate(token.created_at)}
                        {' · '}
                        Last used {formatDate(token.last_used_at)}
                      </p>
                    </div>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="text-destructive hover:text-destructive"
                      onClick={() => setTokenToRevoke(token)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Dialog open={!!newToken} onOpenChange={(open) => !open && setNewToken(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Token Created</DialogTitle>
              <DialogDescription>
                Copy your token now. You won't be able to see it again.
              </DialogDescription>
            </DialogHeader>
            <div className="flex items-center gap-2">
              <code className="flex-1 rounded-md bg-muted p-3 text-sm break-all font-mono">
                {newToken}
              </code>
              <Button variant="outline" size="icon" onClick={handleCopy}>
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            {copied && (
              <p className="text-sm text-green-600 dark:text-green-400">Copied to clipboard!</p>
            )}
          </DialogContent>
        </Dialog>

        <AlertDialog open={!!tokenToRevoke} onOpenChange={(open) => !open && setTokenToRevoke(null)}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Revoke token?</AlertDialogTitle>
              <AlertDialogDescription>
                This will permanently revoke <strong>{tokenToRevoke?.name}</strong>. Any integrations using this token will stop working.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={handleRevoke} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                Revoke
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </div>
  )
}
