# Docs Sync Status

Last synced: `3890ce4e025fef3adec2dd93c616e3eb53912b0d`
Date: 2026-03-20

## Usage

To see what changed in source code since docs were last synced:

```bash
git log 3890ce4e025fef3adec2dd93c616e3eb53912b0d..HEAD --oneline -- include/ js/ scss/
```

When updating docs, replace the hash and date above with the new HEAD:

```bash
git rev-parse HEAD
```
