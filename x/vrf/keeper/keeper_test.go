package keeper_test

import (
	"crypto/rand"
	"testing"

	"github.com/ProtonMail/go-ecvrf/ecvrf"
	"github.com/stretchr/testify/require"

	storetypes "github.com/cosmos/cosmos-sdk/store/v2/types"

	"github.com/cosmos/cosmos-sdk/codec"
	codectypes "github.com/cosmos/cosmos-sdk/codec/types"
	"github.com/cosmos/cosmos-sdk/runtime"
	"github.com/cosmos/cosmos-sdk/testutil"
	sdk "github.com/cosmos/cosmos-sdk/types"

	steroidaddress "github.com/beepxtra/steroid-core4.0/app/address"
	"github.com/beepxtra/steroid-core4.0/x/vrf/keeper"
	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

func setupKeeper(t *testing.T) (keeper.Keeper, sdk.Context) {
	t.Helper()
	k, _, ctx := setupKeeperWithStaking(t)
	return k, ctx
}

func setupKeeperWithStaking(t *testing.T) (keeper.Keeper, *fakeStakingKeeper, sdk.Context) {
	t.Helper()
	key := storetypes.NewKVStoreKey(types.StoreKey)
	tkey := storetypes.NewTransientStoreKey("transient_test")
	ctx := testutil.DefaultContext(key, tkey)

	cdc := codec.NewProtoCodec(codectypes.NewInterfaceRegistry())
	staking := newFakeStakingKeeper()
	k := keeper.NewKeeper(cdc, runtime.NewKVStoreService(key), steroidaddress.Codec{}, staking)
	return k, staking, ctx
}

func genVRFPubKey(t *testing.T) []byte {
	t.Helper()
	_, pk := genKeypair(t)
	return pk
}

func genKeypair(t *testing.T) (sk, pk []byte) {
	t.Helper()
	priv, err := ecvrf.GenerateKey(rand.Reader)
	require.NoError(t, err)
	pub, err := priv.Public()
	require.NoError(t, err)
	return priv.Bytes(), pub.Bytes()
}

// genValAddr returns a random, validly base58-encoded address string — the
// codec rejects arbitrary test strings that aren't valid base58 or bech32.
func genValAddr(t *testing.T) string {
	t.Helper()
	raw := make([]byte, 20)
	_, err := rand.Read(raw)
	require.NoError(t, err)
	return steroidaddress.Base58Encode(raw)
}

func TestRegisterAndGetKey(t *testing.T) {
	k, ctx := setupKeeper(t)
	valAddr := genValAddr(t)
	pubKey := genVRFPubKey(t)

	found, err := k.HasKey(ctx, valAddr)
	require.NoError(t, err)
	require.False(t, found, "no key should be registered yet")

	require.NoError(t, k.RegisterKey(ctx, valAddr, pubKey, 10))

	entry, found, err := k.GetKey(ctx, valAddr)
	require.NoError(t, err)
	require.True(t, found)
	require.Equal(t, valAddr, entry.ValidatorAddress)
	require.Equal(t, pubKey, entry.VrfPubKey)
	require.Equal(t, int64(10), entry.RegisteredAtHeight)
}

func TestRegisterKeyRotation(t *testing.T) {
	k, ctx := setupKeeper(t)
	valAddr := genValAddr(t)

	firstKey := genVRFPubKey(t)
	require.NoError(t, k.RegisterKey(ctx, valAddr, firstKey, 10))

	secondKey := genVRFPubKey(t)
	require.NoError(t, k.RegisterKey(ctx, valAddr, secondKey, 20))

	entry, found, err := k.GetKey(ctx, valAddr)
	require.NoError(t, err)
	require.True(t, found)
	require.Equal(t, secondKey, entry.VrfPubKey, "second registration must overwrite the first (key rotation)")
	require.Equal(t, int64(20), entry.RegisteredAtHeight)
}

func TestGetKeyNotFound(t *testing.T) {
	k, ctx := setupKeeper(t)
	_, found, err := k.GetKey(ctx, genValAddr(t))
	require.NoError(t, err)
	require.False(t, found)
}

func TestGenesisRoundTrip(t *testing.T) {
	k, ctx := setupKeeper(t)

	addrA, addrB := genValAddr(t), genValAddr(t)
	genState := types.GenesisState{
		ValidatorVrfKeys: []*types.ValidatorVRFKey{
			{ValidatorAddress: addrA, VrfPubKey: genVRFPubKey(t), RegisteredAtHeight: 1},
			{ValidatorAddress: addrB, VrfPubKey: genVRFPubKey(t), RegisteredAtHeight: 2},
		},
	}
	k.InitGenesis(ctx, genState)

	exported := k.ExportGenesis(ctx)
	require.Len(t, exported.ValidatorVrfKeys, 2)

	byAddr := make(map[string]*types.ValidatorVRFKey, 2)
	for _, e := range exported.ValidatorVrfKeys {
		byAddr[e.ValidatorAddress] = e
	}
	require.Equal(t, genState.ValidatorVrfKeys[0].VrfPubKey, byAddr[addrA].VrfPubKey)
	require.Equal(t, genState.ValidatorVrfKeys[1].VrfPubKey, byAddr[addrB].VrfPubKey)
}
