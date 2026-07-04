#!/usr/bin/env bash
# Generates Go code from proto/ definitions using buf + protoc-gen-gocosmos.
#
# Third-party proto files (cosmos_proto, cosmos/msg/v1, gogoproto, google/api)
# are vendored under proto/ rather than fetched from the Buf Schema Registry,
# so this script has no network dependency beyond the Go module proxy needed
# to install the two tools below.
set -euo pipefail

cd "$(dirname "$0")/.."

GOBIN="${GOBIN:-$(go env GOPATH)/bin}"
export PATH="$GOBIN:$PATH"

command -v buf >/dev/null || go install github.com/bufbuild/buf/cmd/buf@v1.71.0
command -v protoc-gen-gocosmos >/dev/null || go install github.com/cosmos/gogoproto/protoc-gen-gocosmos@v1.7.0

MODULE="github.com/beepxtra/steroid-core4.0"

(cd proto && buf generate)

# The gocosmos plugin writes output paths from each file's `go_package`
# option, rooted at buf.gen.yaml's `out: ..` (the repo root). For our own
# module that produces a nested github.com/beepxtra/... tree; move the
# generated files into place and discard everything else (the vendored
# third-party protos already have hand-written Go bindings from their
# respective Go modules — we only need output for our own package).
if [ -d "${MODULE}" ]; then
  cp -r "${MODULE}"/x/vrf/types/*.pb.go x/vrf/types/
  rm -rf "$(echo "${MODULE}" | cut -d/ -f1)"
fi
rm -rf google
