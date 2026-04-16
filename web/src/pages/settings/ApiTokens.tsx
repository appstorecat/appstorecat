import { useState, useEffect } from 'react'
import axios from '@/lib/axios'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Copy, Trash2, Plus, Key } from 'lucide-react'

interface ApiToken {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  created_at: string
}

function formatDate(iso: string | null) {
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
  const [tokens, setTokens] = useState<ApiToken[]>([])
  const [loading, setLoading] = useState(true)
  const [name, setName] = useState('')
  const [creating, setCreating] = useState(false)
  const [error, setError] = useState('')
  const [newToken, setNewToken] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)

  const fetchTokens = async () => {
    try {
      const { data } = await axios.get('/account/api-tokens')
      setTokens(data)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchTokens()
  }, [])

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    setCreating(true)
    setError('')
    try {
      const { data } = await axios.post('/account/api-tokens', { name })
      setNewToken(data.plain_text_token)
      setName('')
      fetchTokens()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      setError(msg || 'Failed to create token.')
    } finally {
      setCreating(false)
    }
  }

  const handleRevoke = async (id: number) => {
    if (!confirm('Are you sure you want to revoke this token?')) return
    try {
      await axios.delete(`/account/api-tokens/${id}`)
      setTokens((prev) => prev.filter((t) => t.id !== id))
    } catch {
      // silently fail
    }
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
              <Button type="submit" disabled={creating || !name.trim()}>
                <Plus className="mr-2 h-4 w-4" />
                {creating ? 'Creating...' : 'Create'}
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
                      onClick={() => handleRevoke(token.id)}
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
      </div>
    </div>
  )
}
