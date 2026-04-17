import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { AxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLogo from '@/components/AppLogo'

interface ValidationErrors {
  [key: string]: string[]
}

export default function Login() {
  const navigate = useNavigate()
  const token = useAuthStore((s) => s.token)
  const login = useAuthStore((s) => s.login)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({})
  const [loading, setLoading] = useState(false)

  if (token) {
    return <Navigate to="/discovery/trending" replace />
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setLoading(true)
    try {
      await login(email, password)
      navigate('/discovery/trending')
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 422) {
        const data = err.response.data
        setError(data.message || 'Validation failed.')
        setFieldErrors(data.errors || {})
      } else {
        setError('Invalid credentials.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex justify-center">
          <AppLogo />
        </div>

        <Card className="p-6">
          <div className="mb-5 text-center">
            <h1 className="text-xl font-semibold">Welcome back</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Log in to continue to your dashboard
            </p>
          </div>

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            {error && (
              <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                {error}
              </div>
            )}

            <div className="grid gap-2">
              <Label htmlFor="email">Email address</Label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@example.com"
                autoComplete="email"
                autoFocus
                required
              />
              {fieldErrors.email?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">
                  {msg}
                </p>
              ))}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                autoComplete="current-password"
                required
              />
              {fieldErrors.password?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">
                  {msg}
                </p>
              ))}
            </div>

            <Button type="submit" className="mt-2 w-full" disabled={loading}>
              {loading ? 'Logging in…' : 'Log in'}
            </Button>
          </form>
        </Card>

        <p className="mt-5 text-center text-sm text-muted-foreground">
          Don't have an account?{' '}
          <Link to="/register" className="underline underline-offset-4 hover:text-foreground">
            Sign up
          </Link>
        </p>
      </div>
    </div>
  )
}
