# Sample marketplace extensions

These two extensions are intentionally small and safe to use for marketplace
testing. Each package has a unique route so you can confirm when it is enabled.

## Publish

Create two hosted apps with app type `smliser-extension`, using the matching
slugs below. Upload the package ZIP from `packages/` and its matching
`app.json` from `marketplace-metadata/<slug>/`:

- `marketplace-test-greeter` — standalone extension.
- `marketplace-test-greeter-addon` — depends on the greeter extension.

The ZIP contains a single top-level folder named after its slug, as required by
the extension installer. Keep that folder name and the manifest slug unchanged.

## Install and try them

```sh
smliser extension install marketplace-test-greeter
smliser extension install marketplace-test-greeter-addon
smliser extension list
```

Installing does not enable either extension. Enabling the add-on before its
dependency should fail. Then enable both:

```sh
smliser extension enable marketplace-test-greeter
smliser extension enable marketplace-test-greeter-addon
```

Visit `/sample-extensions/hello` and `/sample-extensions/companion` to confirm
their routes are active. The add-on route uses a class provided by the greeter,
so it also demonstrates dependency load order. Disabling the greeter while the
add-on remains enabled should be refused.

You can also use `smliser extension check`, `update <slug>`, `remove <slug>`, and
`publish` to try the other management paths. `update` is useful after publishing
a higher package version for either slug.
