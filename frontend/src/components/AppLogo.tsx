export default function AppLogo() {
  return (
    <div className="flex items-center gap-2">
      <img
        src="/appstorecat-icon.png"
        alt="AppStoreCat"
        className="h-8 w-8 rounded-md"
      />
      <div className="grid leading-tight">
        <span className="truncate text-sm font-semibold">AppStoreCat</span>
        <span className="truncate text-[10px] text-muted-foreground">App Intelligence Toolkit</span>
      </div>
    </div>
  )
}
