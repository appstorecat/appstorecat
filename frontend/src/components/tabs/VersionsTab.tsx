import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { History } from 'lucide-react'

interface AppVersionData {
  id: number
  version: string
  release_date: string | null
  whats_new: string | null
  file_size_bytes: number | null
  created_at: string
}

interface VersionsTabProps {
  versions: AppVersionData[]
}

function formatBytes(bytes: number | null): string {
  if (!bytes) return '\u2014'
  const mb = bytes / (1024 * 1024)
  return `${mb.toFixed(1)} MB`
}

export default function VersionsTab({ versions }: VersionsTabProps) {
  if (versions.length === 0) {
    return (
      <div className="py-12 text-center">
        <History className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">No version history available.</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {versions.map((version) => (
        <Card key={version.id}>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-base">v{version.version}</CardTitle>
            <div className="flex gap-2">
              {version.release_date && (
                <Badge variant="outline">{version.release_date}</Badge>
              )}
              {version.file_size_bytes && (
                <Badge variant="secondary">{formatBytes(version.file_size_bytes)}</Badge>
              )}
            </div>
          </CardHeader>
          {version.whats_new && (
            <CardContent>
              <p className="whitespace-pre-line text-sm text-muted-foreground">
                {version.whats_new}
              </p>
            </CardContent>
          )}
        </Card>
      ))}
    </div>
  )
}
