# Marketplace Test Greeter Add-on

Marketplace Test Greeter Add-on is a sample extension that demonstrates
extension dependencies. It depends on **Marketplace Test Greeter** and adds a
public endpoint at `/sample-extensions/companion` that uses the greeter's
shared class.

## Install

Install both extensions, then enable the greeter before the add-on:

```sh
smliser extension install marketplace-test-greeter
smliser extension install marketplace-test-greeter-addon
smliser extension enable marketplace-test-greeter
smliser extension enable marketplace-test-greeter-addon
```

After enabling both, visit `/sample-extensions/companion`. Enabling this
extension before its dependency should fail. Disabling the greeter while this
add-on is enabled should also fail.

## Requirements

- Smart License Server with SMLISER extension support.
- Marketplace Test Greeter (`marketplace-test-greeter`) enabled.

## Testing

Use this extension to try marketplace listing, signed package download,
dependency validation, installation, enabling, updating, disabling, removal,
and asset publishing. It does not change application data.
