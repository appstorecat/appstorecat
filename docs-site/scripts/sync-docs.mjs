#!/usr/bin/env node
/**
 * sync-docs.mjs — Mirror ../docs/en/ into src/content/docs/.
 *
 * Why: The canonical Markdown lives in /docs/en/ (rendered as-is on GitHub).
 * Starlight needs frontmatter (title, description) and a content collection
 * directory. This script does the minimal bridging:
 *   1. Copies every .md file from ../docs/en/ to src/content/docs/<same-path>
 *   2. Adds frontmatter (title from first H1; description from first paragraph)
 *      if the file has none yet.
 *   3. Skips files that already have frontmatter, so manual overrides win.
 *
 * Run before `astro dev` / `astro build`. Idempotent — safe to re-run.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync, rmSync, cpSync } from 'node:fs'
import { readdir } from 'node:fs/promises'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const ROOT = join(__dirname, '..')
const REPO_ROOT = join(ROOT, '..')
const SRC = join(REPO_ROOT, 'docs', 'en')
const DEST = join(ROOT, 'src', 'content', 'docs')
const SCREENSHOTS_SRC = join(REPO_ROOT, 'screenshots')
const SCREENSHOTS_DEST = join(ROOT, 'public', 'screenshots')

if (!existsSync(SRC)) {
  console.error(`[sync-docs] Source not found: ${SRC}`)
  process.exit(1)
}

async function* walk(dir) {
  const entries = await readdir(dir, { withFileTypes: true })
  for (const entry of entries) {
    const full = join(dir, entry.name)
    if (entry.isDirectory()) {
      yield* walk(full)
    } else if (entry.isFile() && entry.name.endsWith('.md')) {
      yield full
    }
  }
}

function deriveTitle(body, fallback) {
  const h1 = body.match(/^#\s+(.+?)\s*$/m)
  return h1 ? h1[1].trim() : fallback
}

function deriveDescription(body) {
  // first non-heading, non-blockquote, non-empty paragraph
  const lines = body.split('\n')
  let buf = []
  for (const line of lines) {
    const t = line.trim()
    if (!t) {
      if (buf.length) break
      continue
    }
    if (t.startsWith('#') || t.startsWith('>') || t.startsWith('|') || t.startsWith('```')) {
      if (buf.length) break
      continue
    }
    buf.push(t)
    if (buf.join(' ').length > 200) break
  }
  return buf
    .join(' ')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/[*_`]/g, '')
    .slice(0, 200)
    .trim()
}

function ensureFrontmatter(content, fallbackTitle) {
  if (content.startsWith('---\n')) return content
  const title = deriveTitle(content, fallbackTitle).replace(/"/g, "'")
  const description = deriveDescription(content).replace(/"/g, "'")
  // Strip the leading H1 to avoid duplicate page titles (Starlight renders the frontmatter title)
  const stripped = content.replace(/^#\s+.+?\s*\n+/, '')
  const frontmatter =
    description.length > 10
      ? `---\ntitle: "${title}"\ndescription: "${description}"\n---\n\n`
      : `---\ntitle: "${title}"\n---\n\n`
  return frontmatter + stripped
}

/**
 * Rewrite repo-relative asset paths so the rendered site can resolve them.
 *
 * In `docs/en/features/foo.md`, an author writes
 *   ![alt](../../../screenshots/foo.jpeg)
 * That works on github.com but breaks under Astro's content-collection
 * relative resolver. We copy `screenshots/` into `docs-site/public/screenshots/`
 * and rewrite every reference to a root-relative `/screenshots/foo.jpeg`,
 * which resolves correctly under any `base` path.
 */
function rewriteAssetPaths(content) {
  return content
    .replace(/\]\((?:\.\.\/)+screenshots\//g, '](/screenshots/')
    .replace(/\]\(screenshots\//g, '](/screenshots/')
}

/**
 * Map code fence languages that Shiki's default bundle doesn't ship to
 * close-enough cousins. Avoids build warnings without pulling extra grammars.
 */
const LANG_ALIASES = {
  caddy: 'nginx',
  env: 'bash',
  dotenv: 'bash',
  Caddyfile: 'nginx',
}

function rewriteCodeFences(content) {
  return content.replace(/^```([A-Za-z0-9_-]+)/gm, (match, lang) => {
    return LANG_ALIASES[lang] ? '```' + LANG_ALIASES[lang] : match
  })
}

// The page we want to land users on directly — installation. Skip its
// canonical /getting-started/installation/ output; the same content
// renders at the docs root instead.
const HOMEPAGE_SOURCE = 'getting-started/installation.md'

async function run() {
  if (existsSync(DEST)) rmSync(DEST, { recursive: true, force: true })
  mkdirSync(DEST, { recursive: true })

  let count = 0
  let homepageWritten = false
  for await (const file of walk(SRC)) {
    const rel = relative(SRC, file)
    const raw = readFileSync(file, 'utf8')
    const fallback = rel.replace(/\.md$/, '').split('/').pop().replace(/-/g, ' ')
    const withFrontmatter = ensureFrontmatter(
      raw,
      fallback.charAt(0).toUpperCase() + fallback.slice(1),
    )
    const withFixedAssets = rewriteAssetPaths(withFrontmatter)
    const withFixedLangs = rewriteCodeFences(withFixedAssets)

    if (rel === HOMEPAGE_SOURCE) {
      // Promote installation to the docs root and skip its old slug.
      const homepageOut = join(DEST, 'index.md')
      writeFileSync(homepageOut, withFixedLangs, 'utf8')
      homepageWritten = true
      count++
      continue
    }

    const out = join(DEST, rel)
    mkdirSync(dirname(out), { recursive: true })
    writeFileSync(out, withFixedLangs, 'utf8')
    count++
  }

  if (!homepageWritten) {
    throw new Error(`[sync-docs] expected homepage source at ${HOMEPAGE_SOURCE}`)
  }

  // Mirror screenshots/ into public/screenshots/ so docs images work
  let screenshotCount = 0
  if (existsSync(SCREENSHOTS_SRC)) {
    if (existsSync(SCREENSHOTS_DEST)) {
      rmSync(SCREENSHOTS_DEST, { recursive: true, force: true })
    }
    cpSync(SCREENSHOTS_SRC, SCREENSHOTS_DEST, { recursive: true })
    const fs = await import('node:fs/promises')
    const list = await fs.readdir(SCREENSHOTS_SRC)
    screenshotCount = list.filter((f) => /\.(png|jpe?g|webp|svg)$/i.test(f)).length
  }

  console.log(
    `[sync-docs] Synced ${count} pages (incl. homepage from ${HOMEPAGE_SOURCE}) + ${screenshotCount} screenshots`,
  )
}

// (Kept here in case we want to re-introduce a splash homepage later.)
// — no template needed; the installation page is now the homepage.


run().catch((err) => {
  console.error('[sync-docs] Failed:', err)
  process.exit(1)
})
