---
name: pr-prepare
description: >-
  Generate PR title (English, Conventional Commits) and description (Russian,
  from project template) by analyzing branch commits and diff. Use when preparing
  a pull request, asking "prepare PR", "generate PR description", or "PR title".
argument-hint: "[source-branch] [target-branch]"
user-invocable: true
disable-model-invocation: false
allowed-tools: Bash(git *) Read AskUserQuestion
metadata:
  author: aif-skill-generator
  version: "1.0"
  category: git-workflow
---

# PR Prepare

Generate a pull request title and description by analyzing branch changes.

**Output language rules:**
- Title: English, Conventional Commits format
- Description: Russian, following `.github/PULL_REQUEST_TEMPLATE.md`

## Workflow

### Step 1: Determine Branches

Parse arguments from `$ARGUMENTS`:
- `$1` → source branch (default: current branch via `git branch --show-current`)
- `$2` → target branch (default: `main`)

Present the detected branches to the user for confirmation:

```
Source: feature/my-branch (текущая)
Target: main

Подтверждаете? Или укажите другие ветки.
```

Use `AskUserQuestion` with the detected defaults as the recommended option.

### Step 2: Gather Change Data

Run these commands in parallel to collect all relevant data:

```bash
# Commit log between target and source
git log <target>..<source> --oneline --no-merges

# Detailed commit messages (for understanding intent)
git log <target>..<source> --no-merges --format="%h %s%n%b---"

# File change summary
git diff <target>...<source> --stat

# Full diff for analysis (limit to avoid context overflow)
git diff <target>...<source> --no-color
```

**If the full diff is too large** (more than 3000 lines), use `--stat` plus targeted diffs:
```bash
git diff <target>...<source> --stat
git diff <target>...<source> -- <most-changed-files> --no-color
```

### Step 3: Read PR Template

Read the PR template:
```
.github/PULL_REQUEST_TEMPLATE.md
```

If the template file does not exist, use this default structure:
```markdown
## Описание
## Тип изменения
## Связанные issues
## Чеклист
```

### Step 4: Analyze and Classify

Based on the commits and diff, determine:

1. **Change type** — map to one of the template checkboxes:
   - `fix:` commits → Bug fix
   - `feat:` commits → New feature
   - `refactor:` commits → Refactoring
   - Breaking changes (look for `!` suffix or `BREAKING CHANGE` in body) → Breaking change
   - `docs:` commits → Documentation
   - `chore:`, `ci:`, `build:` commits → Chore
   - Mixed types → check multiple boxes, primary type goes to title

2. **Scope** — identify the primary module/area from commit scopes and changed paths

3. **Breaking changes** — scan commit messages for `BREAKING CHANGE:` footer or `!` in type

### Step 5: Generate Title

Create a Conventional Commits title in English:

**Format:** `type(scope): short description`

**Rules:**
- `type`: feat, fix, refactor, docs, test, chore, ci, build, perf, style
- `scope`: optional, lowercase, from the primary area of change
- Description: imperative mood, lowercase start, no period, under 70 chars total
- If breaking change: add `!` after scope — `type(scope)!: description`
- If multiple types: use the dominant one for the title

**Examples:**
- `feat(loader): add BroadcastLoader for channels.php`
- `refactor!: rewrite core to contract-driven architecture`
- `fix(manifest): prevent data loss on concurrent writes`

### Step 6: Generate Description

Fill the PR template in Russian:

**Описание section:**
- 2-5 sentences explaining WHAT changed and WHY
- Focus on motivation and impact, not file listing
- Use the commit messages as source material but synthesize, don't copy

**Тип изменения section:**
- Check the appropriate box(es) based on Step 4 analysis
- Use `[x]` for checked, `[ ]` for unchecked

**Связанные issues section:**
- Extract issue references from commit messages (e.g., `#123`, `Closes #456`)
- If none found, leave the comment placeholder

**Чеклист section:**
- Leave all items unchecked — the author will verify manually

### Step 7: Present Output

Output the result in a ready-to-use format:

```
## PR Title

<generated title>

## PR Description

<generated description following the template>
```

Add a separator and the `gh` command the user can copy-paste:

```bash
gh pr create --title "<title>" --base <target> --head <source> --body "$(cat <<'EOF'
<description>
EOF
)"
```

### Edge Cases

- **Single commit**: title = commit message (reformatted if needed), description still generated
- **No conventional commits**: infer type from diff content (new files → feat, deleted → refactor, etc.)
- **Huge diff**: summarize by area instead of listing every change
- **Unpushed branch**: warn that `gh pr create` requires pushing first
