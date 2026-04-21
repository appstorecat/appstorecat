import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import {
  useUpdateProfile,
  useUpdatePassword,
  useDeleteProfile,
} from '@/api/endpoints/account/account'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { KeyRound, Webhook } from 'lucide-react'

function ProfileSection() {
  const { user, fetchUser } = useAuthStore()
  const [name, setName] = useState(user?.name ?? '')
  const [email, setEmail] = useState(user?.email ?? '')
  const [success, setSuccess] = useState('')
  const [error, setError] = useState('')

  const updateProfile = useUpdateProfile({
    mutation: {
      onSuccess: async () => {
        await fetchUser()
        setSuccess('Profile updated.')
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setError(msg || 'Failed to update profile.')
      },
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    updateProfile.mutate({ data: { name, email } })
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Profile</CardTitle>
        <CardDescription>Update your name and email address.</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          {error && <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">{error}</div>}
          {success && <div className="rounded-md bg-green-500/10 p-3 text-sm text-green-600 dark:text-green-400">{success}</div>}
          <div className="grid gap-2">
            <Label htmlFor="name">Name</Label>
            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="email">Email</Label>
            <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>
          <Button type="submit" disabled={updateProfile.isPending}>
            {updateProfile.isPending ? 'Saving...' : 'Save'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

function PasswordSection() {
  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [success, setSuccess] = useState('')
  const [error, setError] = useState('')

  const updatePassword = useUpdatePassword({
    mutation: {
      onSuccess: () => {
        setCurrentPassword('')
        setPassword('')
        setPasswordConfirmation('')
        setSuccess('Password updated.')
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setError(msg || 'Failed to update password.')
      },
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    updatePassword.mutate({
      data: {
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      },
    })
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Password</CardTitle>
        <CardDescription>Change your password.</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          {error && <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">{error}</div>}
          {success && <div className="rounded-md bg-green-500/10 p-3 text-sm text-green-600 dark:text-green-400">{success}</div>}
          <div className="grid gap-2">
            <Label htmlFor="current_password">Current Password</Label>
            <Input id="current_password" type="password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} required />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="new_password">New Password</Label>
            <Input id="new_password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="password_confirmation">Confirm New Password</Label>
            <Input id="password_confirmation" type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} required />
          </div>
          <Button type="submit" disabled={updatePassword.isPending}>
            {updatePassword.isPending ? 'Updating...' : 'Update Password'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

function DeleteAccountSection() {
  const navigate = useNavigate()
  const { reset } = useAuthStore()
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')

  const deleteProfile = useDeleteProfile({
    mutation: {
      onSuccess: () => {
        reset()
        navigate('/login')
      },
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setError(msg || 'Failed to delete account.')
      },
    },
  })

  const handleDelete = (e: React.FormEvent) => {
    e.preventDefault()
    if (!confirm('Are you sure you want to delete your account? This action cannot be undone.')) return
    setError('')
    deleteProfile.mutate({ data: { password } })
  }

  return (
    <Card className="border-destructive/30">
      <CardHeader>
        <CardTitle className="text-destructive">Delete Account</CardTitle>
        <CardDescription>
          Permanently delete your account and all associated data. This action cannot be undone.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleDelete} className="space-y-4">
          {error && <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">{error}</div>}
          <div className="grid gap-2">
            <Label htmlFor="delete_password">Confirm Password</Label>
            <Input id="delete_password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </div>
          <Button type="submit" variant="destructive" disabled={deleteProfile.isPending}>
            {deleteProfile.isPending ? 'Deleting...' : 'Delete Account'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

export default function Settings() {
  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      <h1 className="text-2xl font-bold">Settings</h1>
      <div className="mx-auto w-full max-w-2xl space-y-6">
        <ProfileSection />
        <PasswordSection />
        <Separator />
        <div className="grid gap-4 sm:grid-cols-2">
          <Link to="/settings/api-tokens">
            <Card className="h-full transition-colors hover:border-primary/50">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <KeyRound className="h-4 w-4" />
                  API Tokens
                </CardTitle>
                <CardDescription>
                  Create and manage API tokens for MCP and external integrations.
                </CardDescription>
              </CardHeader>
            </Card>
          </Link>
          <Link to="/settings/mcp">
            <Card className="h-full transition-colors hover:border-primary/50">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Webhook className="h-4 w-4" />
                  MCP Setup
                </CardTitle>
                <CardDescription>
                  Connect Claude Code to your AppStoreCat data via MCP.
                </CardDescription>
              </CardHeader>
            </Card>
          </Link>
        </div>
        <Separator />
        <DeleteAccountSection />
      </div>
    </div>
  )
}
