import { useState, useEffect } from 'react'
import axios from '@/lib/axios'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Copy, Check, Terminal } from 'lucide-react'

interface ApiToken {
  id: number
  name: string
}

export default function Mcp() {
  const [tokens, setTokens] = useState<ApiToken[]>([])
  const [selectedToken, setSelectedToken] = useState('')
  const [copiedCli, setCopiedCli] = useState(false)
  const [copiedJson, setCopiedJson] = useState(false)

  useEffect(() => {
    axios.get('/account/api-tokens').then(({ data }) => {
      setTokens(data)
    })
  }, [])

  const apiUrl = window.location.origin.replace(/:\d+$/, ':7460') + '/api/v1'
  const prodUrl = 'https://server.appstore.cat/api/v1'
  const baseUrl = window.location.hostname === 'localhost' ? apiUrl : prodUrl

  const cliCommand = `claude mcp add appstorecat \\
  -e APPSTORECAT_API_URL=${baseUrl} \\
  -e APPSTORECAT_API_TOKEN=${selectedToken || '<your-token>'} \\
  -- npx -y @appstorecat/mcp`

  const jsonConfig = JSON.stringify(
    {
      mcpServers: {
        appstorecat: {
          command: 'npx',
          args: ['-y', '@appstorecat/mcp'],
          env: {
            APPSTORECAT_API_URL: baseUrl,
            APPSTORECAT_API_TOKEN: selectedToken || '<your-token>',
          },
        },
      },
    },
    null,
    2,
  )

  const handleCopy = async (text: string, setter: (v: boolean) => void) => {
    await navigator.clipboard.writeText(text)
    setter(true)
    setTimeout(() => setter(false), 2000)
  }

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      <h1 className="text-2xl font-bold">MCP Setup</h1>
      <div className="mx-auto w-full max-w-2xl space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>What is MCP?</CardTitle>
            <CardDescription>
              Model Context Protocol (MCP) lets AI tools like Claude Code access your AppStoreCat data directly.
              Ask questions like "What are Instagram's latest store changes?" and get answers from your tracked data.
            </CardDescription>
          </CardHeader>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>1. Select Your API Token</CardTitle>
            <CardDescription>
              {tokens.length === 0
                ? 'You need to create an API token first. Go to API Keys to create one.'
                : 'Choose which token to use for MCP authentication.'}
            </CardDescription>
          </CardHeader>
          {tokens.length > 0 && (
            <CardContent>
              <select
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                value={selectedToken}
                onChange={(e) => setSelectedToken(e.target.value)}
              >
                <option value="">Select a token...</option>
                {tokens.map((t) => (
                  <option key={t.id} value={`<paste-${t.name}-token>`}>
                    {t.name}
                  </option>
                ))}
              </select>
              <p className="mt-2 text-xs text-muted-foreground">
                Note: For security, we can't show your token value here. Paste the token you copied when you created it.
              </p>
            </CardContent>
          )}
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>2. Add to Claude Code</CardTitle>
            <CardDescription>
              Run this command in your terminal:
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="relative">
              <pre className="rounded-md bg-muted p-4 text-sm font-mono overflow-x-auto whitespace-pre-wrap">
                {cliCommand}
              </pre>
              <Button
                variant="ghost"
                size="icon"
                className="absolute top-2 right-2"
                onClick={() => handleCopy(cliCommand, setCopiedCli)}
              >
                {copiedCli ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              </Button>
            </div>
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <Terminal className="h-3 w-3" />
              <span>Requires Node.js 18+ and Claude Code CLI</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Or: Manual JSON Config</CardTitle>
            <CardDescription>
              Add this to your <code className="text-xs">.mcp.json</code> or <code className="text-xs">~/.claude/settings.json</code>:
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="relative">
              <pre className="rounded-md bg-muted p-4 text-sm font-mono overflow-x-auto whitespace-pre-wrap">
                {jsonConfig}
              </pre>
              <Button
                variant="ghost"
                size="icon"
                className="absolute top-2 right-2"
                onClick={() => handleCopy(jsonConfig, setCopiedJson)}
              >
                {copiedJson ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>3. Start Using</CardTitle>
            <CardDescription>
              Once configured, you can ask Claude Code things like:
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li>"Show me Instagram's latest store listing changes"</li>
              <li>"What are the trending apps on App Store right now?"</li>
              <li>"Compare reviews for my tracked apps"</li>
              <li>"List all competitors for my app"</li>
              <li>"What's the dashboard summary?"</li>
            </ul>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
