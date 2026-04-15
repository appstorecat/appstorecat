import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLogo from '@/components/AppLogo'

interface ValidationErrors {
  [key: string]: string[]
}

export default function Register() {
  const navigate = useNavigate()
  const register = useAuthStore((s) => s.register)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({})
  const [loading, setLoading] = useState(false)

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
    <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center gap-4">
          <AppLogo />
          <div className="text-center">
            <h1 className="text-xl font-semibold">Create an account</h1>
            <p className="text-sm text-muted-foreground">
              Enter your details below to create your account
            </p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
          <div className="grid gap-6">
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
                placeholder="Full name"
                autoComplete="name"
                autoFocus
                required
              />
              {fieldErrors.name?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">{msg}</p>
              ))}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="email">Email address</Label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="email@example.com"
                autoComplete="email"
                required
              />
              {fieldErrors.email?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">{msg}</p>
              ))}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Password"
                autoComplete="new-password"
                required
              />
              {fieldErrors.password?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">{msg}</p>
              ))}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input
                id="password_confirmation"
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                placeholder="Confirm password"
                autoComplete="new-password"
                required
              />
              {fieldErrors.password_confirmation?.map((msg) => (
                <p key={msg} className="text-sm text-destructive">{msg}</p>
              ))}
            </div>
            <Button type="submit" className="mt-2 w-full" disabled={loading}>
              {loading ? 'Creating account...' : 'Create account'}
            </Button>
          </div>

          <div className="text-center text-sm text-muted-foreground">
            Already have an account?{' '}
            <Link to="/login" className="underline underline-offset-4 hover:text-foreground">
              Log in
            </Link>
          </div>
        </form>
      </div>
    </div>
  )
}
