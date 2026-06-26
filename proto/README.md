# proto/ — Protobuf Definitions

Protobuf service and message definitions for custom Steroid modules live here.

## Layout (planned)

```
proto/
  steroid/
    assets/
      v1/
        tx.proto
        query.proto
        types.proto
    alias/
      v1/
        tx.proto
        query.proto
        types.proto
    vrf/
      v1/
        types.proto    # VRF proposer rotation types (D1a)
```

## Tooling

Code generation will use `buf` (buf.build). A `buf.yaml` and `buf.gen.yaml` will
be added when the first custom module is specced.

## Reference

The first-stage REST API is documented in `doc/` (apidoc) and the PHP SDK is
in `sdk/php/`. Both serve as the parity contract for the REST compatibility
gateway (D7).
