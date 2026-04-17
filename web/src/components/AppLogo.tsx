export default function AppLogo() {
  return (
    <div className="flex items-center gap-2.5">
      <img
        src="/appstorecat-icon.svg"
        alt="AppStoreCat"
        className="h-7 w-7 rounded-md"
      />
      <div className="flex min-w-0 flex-col leading-none">
        <span className="truncate text-sm font-semibold tracking-tight">appstorecat</span>
        <span className="mt-1 truncate text-[10px] text-muted-foreground">App intelligence</span>
      </div>
    </div>
  )
}
