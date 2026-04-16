import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Copy, Check, Terminal } from 'lucide-react'

export default function Mcp() {
  const [apiUrl, setApiUrl] = useState(() => {
    const origin = window.location.origin
    return window.location.hostname === 'localhost'
      ? origin.replace(/:\d+$/, ':7460') + '/api/v1'
      : 'https://server.appstore.cat/api/v1'
  })
  const [apiToken, setApiToken] = useState('')
  const [copiedCli, setCopiedCli] = useState(false)
  const [copiedJson, setCopiedJson] = useState(false)

  const displayUrl = apiUrl || '<your-api-url>'
  const displayToken = apiToken || '<your-token>'

  const cliCommand = `claude mcp add appstorecat \\
  -e APPSTORECAT_API_URL="${displayUrl}" \\
  -e APPSTORECAT_API_TOKEN="${displayToken}" \\
  -- npx -y @appstorecat/mcp`

  const jsonConfig = JSON.stringify(
    {
      mcpServers: {
        appstorecat: {
          command: 'npx',
          args: ['-y', '@appstorecat/mcp'],
          env: {
            APPSTORECAT_API_URL: displayUrl,
            APPSTORECAT_API_TOKEN: displayToken,
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
            <CardTitle>1. Generate Your MCP Config</CardTitle>
            <CardDescription>
              Enter your API URL and token to generate ready-to-use configuration.
              Create a token in{' '}
              <a href="/settings/api-tokens" className="underline">API Keys</a> if you don't have one.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="api_url">API URL</Label>
              <Input
                id="api_url"
                placeholder="https://server.appstore.cat/api/v1"
                value={apiUrl}
                onChange={(e) => setApiUrl(e.target.value)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="api_token">API Token</Label>
              <Input
                id="api_token"
                placeholder="Paste your token (e.g. 1|abc123...)"
                value={apiToken}
                onChange={(e) => setApiToken(e.target.value)}
              />
            </div>
          </CardContent>
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
            <p className="text-xs text-muted-foreground">
              Already configured? Remove first, then re-add:
            </p>
            <pre className="rounded-md bg-muted p-4 text-sm font-mono overflow-x-auto whitespace-pre-wrap text-muted-foreground">
              {`claude mcp remove appstorecat`}
            </pre>
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
