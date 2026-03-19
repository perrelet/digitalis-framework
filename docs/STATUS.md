# Docs Sync Status

Last synced: `ef0498039beb8f7e661bb6b6cc5af7993a15ee84`
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
