# Installed Claude Code Plugins & Skills

Snapshot of what's installed for this machine/project as of 2026-09-09. This is tooling
metadata (Claude Code config), not application architecture — kept here for reference only,
not part of the app's design/build rules.

## Plugins (marketplace-installed, user scope)

| Plugin | Marketplace | Version |
|---|---|---|
| security-guidance | claude-code-skills | 2.9.0 |
| engineering-advanced-skills | claude-code-skills | 2.9.0 |
| a11y-audit | claude-code-skills | 2.9.0 |
| karpathy-coder | claude-code-skills | 2.9.0 |
| ecc | ecc | 2.1.0 (scoped to `AbangananHubMobile`, not this project) |

Each plugin ships one or more skills — see the full skill list below for what they expose.

## User-level skills (`~/.claude/skills/`, apply to all projects)

agent-browser, banner-design, brainstorming, brand, design, design-system,
dispatching-parallel-agents, executing-plans, finishing-a-development-branch, impeccable,
learned, receiving-code-review, requesting-code-review, skill-creator, slides,
subagent-driven-development, systematic-debugging, test-driven-development, ui-styling,
ui-ux-pro-max, using-git-worktrees, using-superpowers, verification-before-completion,
writing-plans, writing-skills

## Project-level skills (`.claude/skills/`, this repo only — override user-level of the same name)

- `frontend-design`
- `ui-ux-pro-max` (project copy, overrides the user-level one above)
- `AI Design Slop Why It Happens & How to Kill It.md` — loose reference doc, not a proper
  `SKILL.md`, so it isn't directly invokable as a skill

## Hooks configured for this project (`.claude/settings.local.json`)

- **PostToolUse** (Edit/Write/MultiEdit) → runs `impeccable`'s immediate-tier design checks
- **Stop** → runs `impeccable`'s full deep design-rule pass

## Notes

- `installed_plugins.json` and the skill folders above are the source of truth; re-run this
  audit if plugins are added/removed (`claude plugin list` or check
  `~/.claude/plugins/installed_plugins.json`).
- Built-in skills (code-review, run, init, security-review, dataviz, etc.) ship with Claude
  Code itself and aren't listed here since they aren't something that gets installed/removed.
