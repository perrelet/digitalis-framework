# Docs Sync Status

Last synced: `19a4d4856b4f3a5481ca548c3c258142d6cf9f14`
Date: 2026-03-18

## Usage

To see what changed in source code since docs were last synced:

```bash
git log ff95b4cc5f92fabd82e755e7dd15355d1d20a28c..HEAD --oneline -- include/ js/ scss/
```

When updating docs, replace the hash and date above with the new HEAD:

```bash
git rev-parse HEAD
```
