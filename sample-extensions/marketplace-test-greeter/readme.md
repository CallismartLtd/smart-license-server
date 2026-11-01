# Marketplace Test Greeter

Marketplace Test Greeter is a small sample extension for testing Smliser's
hosted extension marketplace. It adds a public text endpoint at
`/sample-extensions/hello` when enabled.

## Install

Find **Marketplace Test Greeter** in the marketplace, or run:

```sh
smliser extension install marketplace-test-greeter
smliser extension enable marketplace-test-greeter
```

Installing and enabling are separate steps. After enabling, visit
`/sample-extensions/hello` to see the greeting.

## Requirements

- Smart License Server with SMLISER extension support.

## Testing

Use this extension to try marketplace listing, signed package download,
installation, enabling, updating, disabling, removal, and asset publishing.
It has no external dependencies and does not change application data.
