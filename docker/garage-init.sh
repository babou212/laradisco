#!/bin/sh
# One-shot Garage bootstrap for local dev: lay out the single node, import a
# deterministic S3 key (so .env can be static), create the bucket and grant it.
# Idempotent — safe to re-run on every `compose up`.
set -eu

# Connects to the running node via rpc_public_addr in the config. This needs
# the node key, so the container must share garage's metadata volume.
G="garage -c /etc/garage.toml"

echo "garage-init: waiting for Garage RPC..."
until $G status >/dev/null 2>&1; do
  sleep 1
done

# Assign + apply layout only while the node is still roleless (fresh volume).
NODE_ID="$($G status 2>/dev/null | awk '/NO ROLE ASSIGNED/{print $1; exit}')"
if [ -n "${NODE_ID:-}" ]; then
  echo "garage-init: assigning layout to node $NODE_ID"
  $G layout assign -z dc1 -c 1G "$NODE_ID"
  $G layout apply --version 1
else
  echo "garage-init: layout already configured, skipping"
fi

# Import the fixed dev credentials. If `key import` is unavailable on this
# Garage build, fall back to `garage key create` and copy the printed
# id/secret into .env instead.
$G key import -n laradisco-dev "$GARAGE_KEY_ID" "$GARAGE_KEY_SECRET" --yes 2>/dev/null \
  || echo "garage-init: key already present (or import unsupported), continuing"

$G bucket create "$GARAGE_BUCKET" 2>/dev/null \
  || echo "garage-init: bucket already exists, continuing"

$G bucket allow --read --write --owner "$GARAGE_BUCKET" --key laradisco-dev || true

echo "garage-init: ready — bucket=$GARAGE_BUCKET key=$GARAGE_KEY_ID"
exit 0
