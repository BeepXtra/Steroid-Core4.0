#!/usr/bin/env bash
# devnet-setup.sh — bootstrap a single-validator steroid devnet.
#
# Usage: scripts/devnet-setup.sh [home_dir]
# Default home: /tmp/steroid-devnet
#
# After this script, start the node with:
#   build/stereodd start --home <home_dir> --minimum-gas-prices 0ubpc

set -euo pipefail

BINARY="${BINARY:-./build/stereodd}"
HOME_DIR="${1:-/tmp/steroid-devnet}"
CHAIN_ID="steroid-devnet-1"
KEY_NAME="devnet-validator"
DENOM="ubpc"
STAKE_AMOUNT="1000000000${DENOM}"
TOTAL_AMOUNT="10000000000${DENOM}"

echo "==> Cleaning previous state"
rm -rf "$HOME_DIR"

echo "==> Initialising chain"
"$BINARY" init devnet-node \
    --chain-id "$CHAIN_ID" \
    --home "$HOME_DIR" 2>/dev/null

echo "==> Setting bond denom to ${DENOM}"
# Replace all 'stake' denom references with ubpc in genesis
sed -i "s/\"stake\"/\"${DENOM}\"/g" "$HOME_DIR/config/genesis.json"

echo "==> Creating validator key"
"$BINARY" keys add "$KEY_NAME" \
    --home "$HOME_DIR" \
    --keyring-backend test

echo "==> Deriving base58 address from secp256k1 pubkey"
PUBKEY_B64=$("$BINARY" keys show "$KEY_NAME" \
    --home "$HOME_DIR" \
    --keyring-backend test \
    --output json 2>/dev/null | python3 -c "
import sys, json
d = json.load(sys.stdin)
# pubkey is like {\"@type\":\"/cosmos.crypto.secp256k1.PubKey\",\"key\":\"...\"}
import json as j2
pk = j2.loads(d['pubkey']) if isinstance(d['pubkey'], str) else d['pubkey']
print(pk['key'])
")

B58ADDR=$(python3 - <<PYEOF
import base64, hashlib

ALPHA = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz"
pubkey_bytes = base64.b64decode("$PUBKEY_B64")
sha = hashlib.sha256(pubkey_bytes).digest()
rmd = hashlib.new('ripemd160'); rmd.update(sha)
addr = rmd.digest()
n = int.from_bytes(addr, 'big')
res = []
while n > 0:
    n, r = divmod(n, 58); res.append(ALPHA[r])
for b in addr:
    if b == 0: res.append(ALPHA[0])
    else: break
print(''.join(reversed(res)))
PYEOF
)
echo "==> Validator base58 address: $B58ADDR"

echo "==> Adding genesis account"
"$BINARY" add-genesis-account "$B58ADDR" "$TOTAL_AMOUNT" \
    --home "$HOME_DIR" \
    --keyring-backend test

echo "==> Creating genesis tx"
"$BINARY" gentx "$KEY_NAME" "$STAKE_AMOUNT" \
    --chain-id "$CHAIN_ID" \
    --home "$HOME_DIR" \
    --keyring-backend test

echo "==> Collecting genesis txs"
"$BINARY" collect-gentxs --home "$HOME_DIR" 2>/dev/null

echo "==> Genesis setup complete (bech32 addresses kept for SDK internal compatibility)"

echo ""
echo "==> Devnet ready at: $HOME_DIR"
echo "==> Start with:"
echo "    $BINARY start --home $HOME_DIR --minimum-gas-prices 0${DENOM}"
