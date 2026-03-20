# Docs Sync Status

Last synced: `543905a0a05ddd36422d0da37e4c834f58bc24c0`
Date: 2026-03-20

## Usage

To see what changed in source code since docs were last synced:

```bash
git log 543905a0a05ddd36422d0da37e4c834f58bc24c0..HEAD --oneline -- include/ js/ scss/
```

When updating docs, replace the hash and date above with the new HEAD:

```bash
git rev-parse HEAD
```
