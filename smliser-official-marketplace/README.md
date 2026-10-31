# Smliser Official Marketplace extension

This extension registers the hosted app types used by the official Smliser
marketplace. Install and enable it only on the official hosting server.

To create an installable package from the project root:

```sh
zip -r smliser-official-marketplace.zip smliser-official-marketplace
```

Upload the ZIP in **Admin → Extensions**, explicitly trust the unsigned local
package if prompted, then enable the extension. Its directory name and the
top-level folder in the ZIP must remain `smliser-official-marketplace`.
