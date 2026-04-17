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

export default function Register() {
  const navigate = useNavigate()
  const token = useAuthStore((s) => s.token)
  const register = useAuthStore((s) => s.register)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
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
      await register(name, email, password, passwordConfirmation)
      navigate('/discovery/trending')
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 422) {
        const data = err.response.data
        setError(data.message || 'Validation failed.')
        setFieldErrors(data.errors || {})
      } else {
        setError('Registration failed. Please try again.')
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
            <h1 className="text-xl font-semibold">Create your account</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Start tracking App Store and Play Store apps
            </p>
          </div>

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            {error && (
              <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                {error}
              </div>
            )}

            <div className="grid gap-2">
              <Label htmlFor="name">Name</Label>
              <Input
                id="name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Your name"
                autoComplete="name"
                autoFocus
                required
              />
              {fieldErrors.name?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">
                  {msg}
                </p>
              ))}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="email">Email address</Label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@example.com"
                autoComplete="email"
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
                autoComplete="new-password"
                required
              />
              {fieldErrors.password?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">
                  {msg}
                </p>
              ))}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input
                id="password_confirmation"
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                placeholder="••••••••"
                autoComplete="new-password"
                required
              />
              {fieldErrors.password_confirmation?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">
                  {msg}
                </p>
              ))}
            </div>

            <Button type="submit" className="mt-2 w-full" disabled={loading}>
              {loading ? 'Creating account…' : 'Create account'}
            </Button>
          </form>
        </Card>

        <p className="mt-5 text-center text-sm text-muted-foreground">
          Already have an account?{' '}
          <Link to="/login" className="underline underline-offset-4 hover:text-foreground">
            Log in
          </Link>
        </p>
      </div>
    </div>
  )
}
